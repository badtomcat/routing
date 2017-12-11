<?php

namespace {

    use Badtomcat\Container;
    use Badtomcat\Http\Response;
    use Badtomcat\Routing\Exception\MethodNotAllowedException;
    use Badtomcat\Routing\RequestContext;
    use Badtomcat\Routing\RouteCollection;
    use Badtomcat\Routing\Router;

    class ResourceTest extends PHPUnit_Framework_TestCase
    {
        public function testAction()
        {
            $container = new Container();
            $test = new Router($container, new RouteCollection());
            $test->resource("rest","resController");
                //->setNamespace("App\\Controller");
            $context = new RequestContext();
            $context->setPathInfo("/rest/create");
            $context->setMethod("get");
            $response = $test->dispatch($context);
            $this->assertEquals($context->getRouteName(),"rest.create");
            $this->assertEquals("create", $response->getContent());

            $context->setPathInfo("/rest/12/edit");
            $context->setMethod("get");
            $response = $test->dispatch($context);
            $this->assertEquals($context->getRouteName(),"rest.edit");
            $this->assertEquals("edit:12", $response->getContent());

            $context->setPathInfo("/rest/12");
            $context->setMethod("PUT");
            $response = $test->dispatch($context);
            $this->assertEquals($context->getRouteName(),"rest.update");
            $this->assertEquals("id:12", $response->getContent());

            $context->setPathInfo("/rest");
            $context->setMethod("get");
            $response = $test->dispatch($context);
            $this->assertEquals($context->getRouteName(),"rest.index");
            $this->assertEquals("index", $response->getContent());

            $context->setPathInfo("/rest/152");
            $context->setMethod("delete");
            $response = $test->dispatch($context);
            $this->assertEquals($context->getRouteName(),"rest.destroy");
            $this->assertEquals("destroy152", $response->getContent());

            $context->setPathInfo("/rest/152");
            $context->setMethod("get");
            $response = $test->dispatch($context);
            $this->assertEquals($context->getRouteName(),"rest.show");
            $this->assertEquals("show:152", $response->getContent());

            $context->setPathInfo("/rest");
            $context->setMethod("post");
            $response = $test->dispatch($context);
            $this->assertEquals($context->getRouteName(),"rest.store");
            $this->assertEquals("store", $response->getContent());
        }

        public function testOnlyExcept()
        {
            $container = new Container();
            $test = new Router($container, new RouteCollection());
            $test->resource("rest","resController",[
                "only" => ["create","store","destroy"]
            ])->setNamespace("\\Ak47\\Controller");
            $context = new RequestContext();
            $context->setPathInfo("/rest/create");
            $context->setMethod("get");
            $response = $test->dispatch($context);
            $this->assertEquals($context->getRouteName(),"rest.create");
            $this->assertEquals("AK-create", $response->getContent());

            try {
                $context->setPathInfo("/rest/152");
                $context->setMethod("get");
                $test->dispatch($context);
                $this->fail();
            } catch (MethodNotAllowedException $e) {
                $this->assertEquals(array('DELETE'), $e->getAllowedMethods());
            }
        }

        public function testMiddlewares()
        {
            $container = new Container();
            $test = new Router($container, new RouteCollection());
            $test->resource("rest","resController",[
                "only" => ["create","store","destroy"]
            ])->setNamespace("\\Ak47\\Controller")->setMiddleware([
                function(RequestContext $requestContext,$next)
                {
                    $requestContext->setParameter("test","a middleware");
                    return $next($requestContext);
                },
                function(RequestContext $requestContext,$next)
                {
                    $requestContext->setParameter("qwe","qex");
                    return $next($requestContext);
                }
                ,
                function(RequestContext $requestContext,$next)
                {
                    /**
                     * @var Response $response;
                     *
                     */
                    $response = $next($requestContext);
                    $response->setContent("override:".$response->getContent());
                    return $response;
                }
            ]);
            $context = new RequestContext();
            $context->setPathInfo("/rest/create");
            $context->setMethod("get");
            $response = $test->dispatch($context);
            $this->assertEquals($context->getRouteName(),"rest.create");
            $this->assertEquals("override:AK-create", $response->getContent());
            $this->assertEquals($context->getParameter("test"),"a middleware");
            $this->assertEquals($context->getParameter("qwe"),"qex");
        }
    }
}
namespace App\Controller
{
    class resController
    {
        public function create()
        {
            return "create";
        }
        public function store()
        {
            return "store";
        }
        public function destroy($id)
        {
            return 'destroy'.$id;
        }
        public function edit($id)
        {
            return "edit:$id";
        }
        public function update($id)
        {
            return "id:$id";
        }
        public function show($id)
        {
            return "show:$id";
        }
        public function index()
        {
            return "index";
        }
    }
}

namespace Ak47\Controller
{
    class resController
    {
        public function create()
        {
            return "AK-create";
        }
        public function store()
        {
            return "AK-store";
        }
        public function destroy($id)
        {
            return 'AK-destroy'.$id;
        }
    }
}

