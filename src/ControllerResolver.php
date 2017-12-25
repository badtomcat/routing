<?php
/**
 * 本类职责是解析路由的第二个参数,执行 action
 * Ccontrol Action 存于Route的Option中
 * Created by PhpStorm.
 * User: awei.tian
 * Date: 9/20/17
 * Time: 8:03 PM
 */

namespace Badtomcat\Routing;

use Badtomcat\Http\Response;
use Badtomcat\Routing\Exception\InvalidActionException;
use Badtomcat\Container;
use Badtomcat\Pipeline;
use Badtomcat\Routing\Exception\MissingMandatoryParametersException;
use ReflectionParameter;

class ControllerResolver
{
    protected $defNamespace = "\\App\\Controller";


    /**
     * @var Container;
     */
    protected $container;

    /**
     * 用于索引
     * All of the short-hand keys for middlewares.
     * [
     *      wmname1 => m1,
     *      wmname2 => m2,
     * ]
     * @var array
     */
    protected $middleware = [];

    /**
     * 用于索引
     * All of the middleware groups.
     * [
     *      web:[
     *          mw1,
     *          mw2
     *      ],
     *
     * ]
     * @var array
     */
    protected $middlewareGroups = [];

    /**
     * The priority-sorted list of middleware.
     * [
     *      mw1,
     *      mw2,
     *      ...
     * ]
     * Forces the listed middleware to always be in the given order.
     *
     * @var array
     */
    public $middlewarePriority = [];

    /***
     * ControllerResolver constructor.
     * @param Container $container
     * @param array $middleware middleware|middlewareGroups|middlewarePriority
     * @param $defNs
     */
    public function __construct(Container $container, $middleware,$defNs)
    {
        $this->container = $container;
        $this->middlewareGroups = $middleware['middlewareGroups'];
        $this->middleware = $middleware['middleware'];
        $this->middlewarePriority = $middleware['middlewarePriority'];
        $this->defNamespace = $defNs;
    }

    /**
     * 如果是MainController@Index格式的
     * 先处理NAMESPACE,可选参数_namespace
     *
     * @param RequestContext $request
     * @param Route $route
     * @return Response
     */
    public function resolve(Route $route, RequestContext $request)
    {
        return $this->middleware($route, $request);
    }

    /**
     * @param Route $route
     * @param RequestContext $request
     * @return Response
     */
    protected function middleware(Route $route, RequestContext $request)
    {
        try {
            $shouldSkipMiddleware = $this->container->bound('middleware.disable') &&
                $this->container->make('middleware.disable') === true;
        } catch (\Exception $e) {
            return new Response('resolve middleware.disable failed.', 500);
        }
        if ($shouldSkipMiddleware) {
            $middlewares = [];
        } else {
            $middlewares = $this->gatherRouteMiddleware($route);
        }
        if ($route->isPassMiddlewarePriority()) {
            $results = [];
        } else {
            $results = $this->middlewarePriority;
        }
        $this->middlewarePriority = [];
        foreach ($middlewares as $middleware) {
            if (is_callable($middleware)) {
                $results[] = $middleware;
            } else if (is_string($middleware)) {
                if (array_key_exists($middleware, $this->middleware)) {
                    $results[] = $this->middleware[$middleware];
                } else if (array_key_exists($middleware, $this->middlewareGroups)) {
                    $results = array_merge($results, $this->middlewareGroups[$middleware]);
                } else if (class_exists($middleware)) {
                    $results[] = $middleware;
                }
            }
        }
        return (new Pipeline())
            ->send($request)
            ->through($results)
            ->then(function ($request) use ($route) {
                $res = $this->execAction(
                    $route, $request
                );
                return $res;
            });
    }

    protected function handleNamespace(Route $route)
    {
        return $route->getNamespace() ? $route->getNamespace() : $this->defNamespace;
    }

    /**
     * @param Route $route
     * @param RequestContext $context
     * @return Response
     */
    protected function execAction(Route $route, RequestContext $context)
    {
        $action = $route->getAction();
        if (is_string($action)) {
            $callback = explode("@", $action);
            if ($callback[0][0] != "\\") {
                $class = $this->handleNamespace($route)."\\".$callback[0];
            } else {
                $class = $callback[0];
            }
            $method = $callback[1];
            if (!class_exists($class)) {
                $e = new InvalidActionException($class . ' Not found. class not exist');
                throw $e;
            }
            try {
                //反射方法实例
                $reflectionMethod = new \ReflectionMethod($class, $method);
                //解析方法参数
                $args = $this->container->getDependencies($reflectionMethod->getParameters(),$context->getParameters());
                //生成类并执行方法
                $content =  $reflectionMethod->invokeArgs($this->container->make($class), $args);
                return new Response($content);
            } catch (\Exception $e) {
                return new Response('Call ' . $class . '::' . $method . ' failed.', 500);
            }
        } elseif (is_callable($action)) {
            $rc = new \ReflectionFunction($action);
            try {
                $arg = $this->resolverParameter($rc, $context);
                $content = call_user_func_array($action, $arg);
                if ($context instanceof Response)
                {
                    return $content;
                }
                return new Response($content);
            } catch (\Exception $e) {
                return new Response($e->getMessage(), 500);
            }
        }
        return new Response("PAGE NOT FOUND", 404);
    }

//    public function group(array $actions,\Closure $call)
//    {
//
//    }


    /**
     * @param Route $route
     * @return array
     */
    private function gatherRouteMiddleware(Route $route)
    {
        $middleware = $route->getMiddlewares();
        if (is_callable($middleware)) {
            return [$middleware];
        } elseif (is_array($middleware)) {
            return $middleware;
        }
        return [];
    }

    /**
     * @param \ReflectionMethod|\ReflectionFunction $rc
     * @param RequestContext $request
     *
     * @return array
     * @throws \Exception
     */
    private function resolverParameter($rc, RequestContext $request)
    {
        $arg = [];
        foreach ($rc->getParameters() as $parameter) {
            $arg[] = $this->fixParameter($parameter,$request);
        }
        return $arg;
    }

    /**
     * 依赖注入,HTTP REQUEST参数注入
     * @param ReflectionParameter $parameter
     * @param RequestContext $request
     * @return Container|mixed|object
     * @throws \Exception
     */
    private function fixParameter(ReflectionParameter $parameter,RequestContext $request)
    {
        if (!is_null($parameter->getClass())) {
            if ($parameter->getClass()->name == Container::class) {
                return $this->container;
            } else {
                return $this->container->make($parameter->getClass()->name);
            }
        } elseif ($request->hasParameter($parameter->getName())) {
            return $request->getParameter($parameter->getName());
        } else {
            throw new MissingMandatoryParametersException();
        }
    }
}