<?php


class RouteTest extends PHPUnit_Framework_TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testloadEnv()
    {
        $test = new \Badtomcat\Routing\Route();
        $test->setPath("/foo/{bar}");
        $test->setHost("{sub}.baidu.com");
        $test->setMethods(["post","get"]);
        $test->where("bar","^\d+$");
        $test->where("sub","^ng-\d+$");
        $c = $test->compile();
//    	var_dump($c);
    }

}




