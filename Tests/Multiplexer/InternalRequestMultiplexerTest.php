<?php

namespace Liip\MultiplexBundle\Tests\Multiplexer;

use Liip\MultiplexBundle\Multiplexer\InternalRequestMultiplexer;

/**
 * @covers Liip\MultiplexBundle\Multiplexer\InternalRequestMultiplexer<extended>
 */
class InternalRequestMultiplexerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * InternalRequestMultiplexer
     */
    protected $multiplexer;

    public function setUp()
    {
        $this->request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->disableOriginalConstructor()->getMock();
        $this->kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Kernel')->disableOriginalConstructor()->getMock();
        $this->router = $this->getMockBuilder('Symfony\Component\Routing\RouterInterface')->disableOriginalConstructor()->getMock();

        $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\Session')->disableOriginalConstructor()->getMock();
        $this->request->expects($this->any())
            ->method('getSession')
            ->with()
            ->will($this->returnValue($session));

        $this->multiplexer = new InternalRequestMultiplexer($this->kernel, $this->router);
        $this->dispatcher = $this->getMock('Liip\MultiplexBundle\Multiplexer\MultiplexDispatcher');
    }

    public function testConstructor()
    {
        $this->assertAttributeSame($this->kernel, 'kernel', $this->multiplexer);
        $this->assertAttributeSame($this->router, 'router', $this->multiplexer);
    }

    public function testSupports()
    {
        $this->assertFalse($this->multiplexer->supports(array('uri' => 'http://google.com')));
        $this->assertTrue($this->multiplexer->supports(array('uri' => '/')));
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    public function testHandleWithRestrictedUri()
    {
        $request = array('uri' => '/foobar', 'method' => 'GET', 'parameters' => array());
        $this->multiplexer->restrictRoutes(true);

        $routeCollection = $this->getMockBuilder('Symfony\Component\Routing\RouteCollection')
            ->disableOriginalConstructor()
            ->setMethods(array('get'))
            ->getMock();

        $route = $this->getMockBuilder('Symfony\Component\Routing\Route')
            ->disableOriginalConstructor()
            ->setMethods(array('getOptions'))
            ->getMock();

        $route->expects($this->atLeastOnce())->method('getOptions')->will($this->returnValue(array()));
        $routeCollection->expects($this->atLeastOnce())->method('get')->will($this->returnValue($route));

        $this->router->expects($this->atLeastOnce())->method('getRouteCollection')->will($this->returnValue($routeCollection));
        $this->router->expects($this->atLeastOnce())->method('match')->with('/foobar')->will($this->returnValue(array('_route'=>'foo')));

        $this->multiplexer->handleRequest($this->request, $request, $this->dispatcher);
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testHandleWithUnknownUri()
    {
        $request = array('uri' => '/foobar', 'method' => 'GET', 'parameters' => array());
        $this->multiplexer->restrictRoutes(true);

        $this->router->expects($this->atLeastOnce())->method('match')->with('/foobar')->will($this->returnValue(false));

        $this->multiplexer->handleRequest($this->request, $request, $this->dispatcher);
    }

    public function testHandleWithValidUri()
    {
        $uri = 'test/uri';
        $match = array('match');
        $sub_content = 'sub content';

        $request = array('uri' => '/' . $uri, 'method' => 'GET', 'parameters' => array());

        $subResponse = $this->getMockBuilder('Symfony\Component\HttpFoundation\Response')->disableOriginalConstructor()->getMock();
        $subResponse->expects($this->once())
            ->method('getContent')
            ->with()
            ->will($this->returnValue($sub_content));
        $subResponse->expects($this->once())
            ->method('isRedirect')
            ->with()
            ->will($this->returnValue(false));

        $subResponse->expects($this->once())
            ->method('getStatusCode')
            ->with()
            ->will($this->returnValue(200));

        $this->router->expects($this->once())
            ->method('match')
            ->with('/' . $uri)
            ->will($this->returnValue($match));

        $this->kernel->expects($this->once())
            ->method('handle')
            ->will($this->returnValue($subResponse));

        $response = $this->multiplexer->handleRequest($this->request, $request, $this->dispatcher);
        $this->assertEquals(array('request'=>'/'.$uri,'status'=>200, 'response'=>'sub content'), $response);
    }
}
