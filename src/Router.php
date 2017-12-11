<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/12/7
 * Time: 15:32
 */

namespace Badtomcat\Routing;


use Badtomcat\Container;
use Badtomcat\Http\Response;

class Router
{
    /**
     * The IoC container instance.
     *
     * @var Container
     */
    protected $container;

    /**
     * All of the verbs supported by the router.
     *
     * @var array
     */
    public static $verbs = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * The route collection instance.
     *
     * @var RouteCollection
     */
    protected $routes;
    /**
     * The currently dispatched route instance.
     *
     * @var Route
     */
    protected $current;

    /**
     * All of the short-hand keys for middlewares.
     *
     * @var array
     */
    public $middleware = [];

    /**
     * All of the middleware groups.
     *
     * @var array
     */
    public $middlewareGroups = [];

    /**
     * The priority-sorted list of middleware.
     *
     * Forces the listed middleware to always be in the given order.
     *
     * @var array
     */
    public $middlewarePriority = [];

    protected $defaultNamespace = '\\App\\Controller';

    public function __construct(Container $app, RouteCollection $coll)
    {
        $this->container = $app;
        $this->routes = $coll;
    }

    /**
     * Get the underlying route collection.
     *
     * @return RouteCollection
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /***
     * @param $middleware
     * @return $this
     */
    public function setGlobalMiddleware($middleware)
    {
        $this->middleware = $middleware;
        return $this;
    }

    /***
     * @param $middleware
     * @return $this
     */
    public function setMiddlewareGroups($middleware)
    {
        $this->middlewareGroups = $middleware;
        return $this;
    }


    /***
     * @param $middleware
     * @return $this
     */
    public function setMiddlewarePriority($middleware)
    {
        $this->middlewarePriority = $middleware;
        return $this;
    }

    /**
     * Set the route collection instance.
     *
     * @param  RouteCollection $routes
     * @return Router
     */
    public function setRoutes(RouteCollection $routes)
    {
        $this->routes = $routes;
        return $this;
    }

    /**
     * @param $para
     * @param $regexp
     * @return $this
     */
    public function where($para, $regexp)
    {
        if (!is_null($this->current)) {
            $this->current->where($para, $regexp);
        }
        return $this;
    }

    /**
     * @param $middleware
     * @return $this
     */
    public function middleware($middleware)
    {
        if (!is_null($this->current)) {
            $this->current->setMiddlewares($middleware);
        }
        return $this;
    }

    /**
     * 设置controller的namespace
     * @param string $ns
     * @return $this
     */
    public function setControllerNamespace($ns)
    {
        if (!is_null($this->current)) {
            $this->current->setNamespace($ns);
        }
        return $this;
    }

    /**
     * @param $oldname
     * @param $newname
     * @return $this
     */
    public function changeName($oldname,$newname)
    {
        $route = $this->routes->get($oldname);
        if (is_null($route)) {
            return $this;
        }
        $this->routes->remove($oldname);
        if ($newname != $route->getName()) {
            $route->name($newname);
        }
        $this->routes->add($route);
        return $this;
    }

    public function setDefaultNamespace($ns)
    {
        $this->defaultNamespace = $ns;
    }

    protected function getName($methods, $uri, $action)
    {
        return (is_array($methods) ? implode("|", $methods) : $methods)
            . '-' . $uri
            . '-' . (is_string($action) ? $action : spl_object_hash($action));
    }

    /**
     * Register a new GET route with the router.
     *
     * @param  string $uri
     * @param  \Closure|array|string|null $action
     * @return Route
     */
    public function get($uri, $action = null)
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    /**
     * Register a new POST route with the router.
     *
     * @param  string $uri
     * @param  \Closure|array|string|null $action
     * @return Route
     */
    public function post($uri, $action = null)
    {
        return $this->addRoute('POST', $uri, $action);
    }

    /**
     * Register a new PUT route with the router.
     *
     * @param  string $uri
     * @param  \Closure|array|string|null $action
     * @return Route
     */
    public function put($uri, $action = null)
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    /**
     * Register a new PATCH route with the router.
     *
     * @param  string $uri
     * @param  \Closure|array|string|null $action
     * @return Route
     */
    public function patch($uri, $action = null)
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    /**
     * Register a new DELETE route with the router.
     *
     * @param  string $uri
     * @param  \Closure|array|string|null $action
     * @return Route
     */
    public function delete($uri, $action = null)
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    /**
     * Register a new OPTIONS route with the router.
     *
     * @param  string $uri
     * @param  \Closure|array|string|null $action
     * @return Route
     */
    public function options($uri, $action = null)
    {
        return $this->addRoute('OPTIONS', $uri, $action);
    }

    /**
     * Register a new route responding to all verbs.
     *
     * @param  string $uri
     * @param  \Closure|array|string|null $action
     * @return Route
     */
    public function any($uri, $action = null)
    {
        return $this->addRoute(self::$verbs, $uri, $action);
    }

    /**
     * Add a route to the underlying route collection.
     *
     * @param  array|string $methods
     * @param  string $uri
     * @param  \Closure|array|string|null $action
     * @return Route
     */
    public function addRoute($methods, $uri, $action)
    {
        $name = $this->getName($methods, $uri, $action);
        $route = $this->newRoute($methods, $uri, $action);
        $route->setRouter($this);
        $this->current = $route;
        $route->name($name);
        return $this->routes->add($route);
    }

    /***
     * @param RequestContext $request
     * @return array
     */
    public function testRequestMatch(RequestContext $request)
    {
        $matcher = new Matcher($this->routes, $request);
        return $matcher->match();
    }
    /**
     * Register a new route with the given verbs.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  \Closure|array|string|null  $action
     * @return Route
     */
    public function match($methods, $uri, $action = null)
    {
        return $this->addRoute(array_map('strtoupper', (array) $methods), $uri, $action);
    }
    /**
     * Dispatch the request to the application.
     *
     * @param  RequestContext $request
     * @return Response
     */
    public function dispatch(RequestContext $request)
    {
        $ret = $this->testRequestMatch($request);
        //设置REQUEST的参数,RET为匹配成功的返回值,里面为定义路由时的PATH和HOST的参数,_route为匹配的路由名字
        //['foo' => 'bar','arg' => 'test','_route' => 'main']
        $request->setRouteName($ret['_route']);
        $request->setParameters(array_merge($request->getParameters(), $ret));
        $route = $this->routes->get($ret['_route']);
        $resolver = new ControllerResolver($this->container, [
            "middlewareGroups" => $this->middlewareGroups,
            "middleware" => $this->middleware,
            "middlewarePriority" => $this->middlewarePriority,
        ], $this->defaultNamespace);
        return $resolver->resolve($route, $request);
    }


    /**
     * Route a resource to a controller.
     *
     * @param  string $name
     * @param  string $controller
     * @param  array $options
     * @return RouteCollection
     * @throws \Exception
     */
    public function resource($name, $controller, array $options = [])
    {
        if ($this->container && $this->container->bound(ResourceRegistrar::class)) {
            $registrar = $this->container->make(ResourceRegistrar::class);
        } else {
            $registrar = new ResourceRegistrar($this);
        }
        return $registrar->register($name, $controller, $options);
    }


    /**
     * Create a new Route object.
     *
     * @param  array|string $methods
     * @param  string $uri
     * @param  mixed $action
     * @return Route
     */
    protected function newRoute($methods, $uri, $action)
    {
        $route = new Route();
        $route->setPath($uri);
        $route->setMethods($methods);
        $route->setAction($action);
        return $route;
    }
}