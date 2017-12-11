<?php


use Badtomcat\Routing\RequestContext;
use Badtomcat\Routing\Exception\MethodNotAllowedException;

class MatcherTest extends PHPUnit_Framework_TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testloadEnv()
    {
    	$test = new \Badtomcat\Routing\Route();
    	$test->name("routeName");
    	$test->setPath("/foo/{bar}");
    	$test->setHost("{sub}.baidu.com");
    	$test->setMethods(["post","get"]);
    	$test->where("bar","^\d+$");
    	$test->where("sub","^ng-\d+$");

    	$col = new \Badtomcat\Routing\RouteCollection();
    	$col->add($test);

    	$request = new RequestContext();
    	$request->setPathInfo("/foo/123");
        $request->setHost("ng-124.baidu.com");
    	$matcher = new \Badtomcat\Routing\Matcher($col,$request);
        $this->assertEquals($test->getName(),"routeName");
    	$ret = $matcher->match();
    	$this->assertEquals($ret["_route"],"routeName");
    	$this->assertEquals($ret["bar"],"123");
    	$this->assertEquals($ret["sub"],"ng-124");
    }


    public function testMethodNotAllowed()
    {
        $test = new \Badtomcat\Routing\Route();
        $test->name("routeName");
        $test->setPath("/foo/{bar}");
        $test->setMethods(["post"]);


        $col = new \Badtomcat\Routing\RouteCollection();
        $col->add($test);

        $request = new RequestContext();
        $request->setPathInfo("/foo/123");
        $matcher = new \Badtomcat\Routing\Matcher($col,$request);
        try {
            $matcher->match();
            $this->fail();
        } catch (MethodNotAllowedException $e) {
            $this->assertEquals(array('POST'), $e->getAllowedMethods());
        }

    }
}

