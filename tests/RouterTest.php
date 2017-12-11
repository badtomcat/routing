<?php

namespace {

    use Badtomcat\Container;
    use Badtomcat\Http\Response;
    use Badtomcat\Routing\RequestContext;
    use Badtomcat\Routing\RouteCollection;
    use Badtomcat\Routing\Router;

    class RouterTest extends PHPUnit_Framework_TestCase
    {

        public function testAction()
        {
            $container = new Container();
            $test = new Router($container, new RouteCollection());
            $test->get("/foo/{bar}", function ($bar) {
                return "sss" . $bar;
            });
            $context = new RequestContext();
            $context->setPathInfo("/foo/did");
            $response = $test->dispatch($context);
            $this->assertEquals("sssdid", $response->getContent());
        }

        public function testStringAction()
        {
            $container = new Container();
            $test = new Router($container, new RouteCollection());
            $test->get("/foo/{bar}", "testController@action");
            $context = new RequestContext();
            $context->setPathInfo("/foo/did");
            $response = $test->dispatch($context);
            $this->assertEquals("balabala", $response->getContent());
        }

        public function testNamespace()
        {
            $container = new Container();
            $test = new Router($container, new RouteCollection());
            $test->setDefaultNamespace("\\Koubei");
            $test->get("/foo/{bar}", "testController@action");
            $context = new RequestContext();
            $context->setPathInfo("/foo/did");
            $response = $test->dispatch($context);
            $this->assertEquals("Koubei-balabala", $response->getContent());
            $test->get("/foo/{bar}", "testController@action")->setNamespace("\\SelfNs");
            $context = new RequestContext();
            $context->setPathInfo("/foo/did");
            $response = $test->dispatch($context);
            $this->assertEquals("SelfNs-balabala", $response->getContent());
        }

        public function testName()
        {
            $container = new Container();
            $test = new Router($container, new RouteCollection());
            $test->setDefaultNamespace("\\Koubei");
            $test->get("/foo/{bar}", "testController@action")->name("oubar");
            $context = new RequestContext();
            $context->setPathInfo("/foo/did");
            $ret = $test->testRequestMatch($context);
            $this->assertEquals("oubar", $ret["_route"]);
        }
        public function testMiddleware()
        {
            $container = new Container();
            $test = new Router($container, new RouteCollection());
            $test->get("/foo/{bar}", "testController@action")->setMiddlewares([
                function(RequestContext $requestContext,$next)
                {
                    $requestContext->setParameter("test","I am a middleware");
                    return $next($requestContext);
                },
                function(RequestContext $requestContext,$next)
                {
                    /**
                     * @var Response $response
                     */
                    $response = $next($requestContext);
                    $response->setContent("force to change");
                    return $response;
                }
            ]);
            $context = new RequestContext();
            $context->setPathInfo("/foo/did");
            $response = $test->dispatch($context);
            $this->assertEquals("force to change", $response->getContent());
            $this->assertEquals($context->getParameter("test"),"I am a middleware");
        }
    }
}
namespace App\Controller
{
    class testController
    {
        public function action()
        {
            return "balabala";
        }
    }
}

namespace Koubei
{
    class testController
    {
        public function action()
        {
            return "Koubei-balabala";
        }
    }
}

namespace SelfNs
{
    class testController
    {
        public function action()
        {
            return "SelfNs-balabala";
        }
    }
}