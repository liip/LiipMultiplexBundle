<?php

namespace Liip\MultiplexBundle\Tests\Multiplexer;

use Liip\MultiplexBundle\Multiplexer\MultiplexDispatcher;
use Liip\MultiplexBundle\Multiplexer\InternalRequestMultiplexer;
use Liip\MultiplexBundle\Multiplexer\ExternalRequestMultiplexer;
use Buzz\Message\Response;

/**
 * @covers Liip\MultiplexBundle\Multiplexer\MultiplexDispatcher
 */
class MultiplexerDispatcherTest extends \PHPUnit_Framework_TestCase
{
    /**
     * InternalRequestMultiplexer
     */
    protected $manager;

    public function setUp()
    {
        $this->request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->disableOriginalConstructor()->getMock();
        $this->request->expects($this->any())->method('getRequestFormat')->will($this->returnValue('json'));
        $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\Session')->disableOriginalConstructor()->getMock();
        $this->request->expects($this->any())
            ->method('getSession')
            ->with()
            ->will($this->returnValue($session));

        $this->manager = new MultiplexDispatcher();
    }

    public function testMultiplexWithDefaults()
    {
        $response = $this->manager->multiplex($this->request);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);
        $this->assertEquals('[]', $response->getContent());
    }

    public function testMultiplexWithJson()
    {
        $response = $this->manager->multiplex($this->request, 'json');

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);
        $this->assertEquals('[]', $response->getContent());
    }

    public function testMultiplexWithHtml()
    {
        $response = $this->manager->multiplex($this->request, 'html');

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
        $this->assertEquals("<pre>array (\n)</pre>", $response->getContent());
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
     */
    public function testMultiplexWithUnknownFormat()
    {
        $response = $this->manager->multiplex($this->request, 'yml');
    }

    public function testMultiplexRequests()
    {
        $this->multiplexFixture(array(
            array('uri' => '', 'method' => 'GET', 'parameters' => array()),
            array('uri' => 'http://google.com', 'method' => 'POST', 'parameters' => array('q'=>'Symfony2')),
            array('uri' => '/foobar', 'method' => 'GET', 'parameters' => array())
        ));

        //external multiplexer
        $buzz = $this->getMockBuilder('\Buzz\Browser')
            ->disableOriginalConstructor()
            ->setMethods(array('submit'))
            ->getMock();
        $postResponse = new Response();
        $postResponse->setContent('bar');
        $buzz->expects($this->atLeastOnce())->method('submit')->will($this->returnValue($postResponse));
        $this->manager->addMultiplexer(new ExternalRequestMultiplexer($buzz));

        //internal multiplexer
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Kernel')->disableOriginalConstructor()->getMock();
        $router = $this->getMockBuilder('Symfony\Component\Routing\RouterInterface')->disableOriginalConstructor()->getMock();
        $router->expects($this->at(0))->method('match')->with('/foobar')->will($this->returnValue(array('_route'=>'foo')));
        $subResponse = $this->getMockBuilder('Symfony\Component\HttpFoundation\Response')->disableOriginalConstructor()->getMock();
        $subResponse->expects($this->once())->method('getContent')->with()->will($this->returnValue('foo'));

        $kernel->expects($this->once())->method('handle')->will($this->returnValue($subResponse));

        $this->manager->addMultiplexer(new InternalRequestMultiplexer($kernel, $router));

        $response = $this->manager->multiplex($this->request);
        $this->assertEquals('{"":{"status":500,"response":"no uri given"},"http:\/\/google.com":{"request":"http:\/\/google.com","status":null,"response":"bar"},"\/foobar":{"request":"\/foobar","status":null,"response":"foo"}}', $response->getContent());
    }

    public function testMultiplexRequestWithHiddenErrorMessages()
    {
        $this->multiplexFixture(array(
            array('uri' => '', 'method' => 'GET', 'parameters' => array()),
            array('uri' => 'http://google.com', 'method' => 'PUT', 'parameters' => array('q'=>'Symfony2')),
            array('uri' => '/foobar', 'method' => 'GET', 'parameters' => array())
        ));

        //external multiplexer
        $buzz = $this->getMockBuilder('\Buzz\Browser')
            ->disableOriginalConstructor()
            ->setMethods(array('submit'))
            ->getMock();
        $this->manager->addMultiplexer(new ExternalRequestMultiplexer($buzz));

        //internal multiplexer
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Kernel')->disableOriginalConstructor()->getMock();
        $router = $this->getMockBuilder('Symfony\Component\Routing\RouterInterface')->disableOriginalConstructor()->getMock();
        $router->expects($this->at(0))->method('match')->with('/foobar')->will($this->returnValue(false));
        $this->manager->addMultiplexer(new InternalRequestMultiplexer($kernel, $router));

        $this->manager->displayErrors(false);
        $response = $this->manager->multiplex($this->request);
        $this->assertEquals('{"":{"status":500,"response":"Internal Server Error"},"http:\/\/google.com":{"status":501,"response":"Not Implemented"},"\/foobar":{"status":404,"response":"Not Found"}}', $response->getContent());
    }

    /*
    public function testMultiplexWithFaultyParameters()
    {
        $this->multiplexFixture(array(
            array('uri' => ''),
            array('uri' => '/'),
            array('uri' => '/foobar')
        ));

        $routeCollection = $this->getMockBuilder('Symfony\Component\Routing\RouteCollection')
            ->disableOriginalConstructor()
            ->setMethods(array('get'))
            ->getMock();

        $route = $this->getMockBuilder('Symfony\Component\Routing\Route')
            ->disableOriginalConstructor()
            ->setMethods(array('getOptions'))
            ->getMock();

        $route->expects($this->atLeastOnce())->method('getOptions')->will($this->returnValue(array('foo' => 'bar')));

        $routeCollection->expects($this->atLeastOnce())->method('get')->will($this->returnValue($route));

        $this->router->expects($this->atLeastOnce())->method('getRouteCollection')->will($this->returnValue($routeCollection));
        $this->router->expects($this->at(2))->method('match')->with('/foobar')->will($this->returnValue(false));
        $this->router->expects($this->at(5))->method('match')->with('/foobar')->will($this->returnValue(false));

        //with error messages on
        $this->manager->setConfig(array(
            'restrict_routes' => true,
            'display_errors' => true
        ));

        $response = $this->manager->multiplex($this->request, 'json');
        $this->assertEquals('{"responses":{"":{"status":500,"response":"no uri given for index: 0"},"\/":{"status":403,"response":"route not able to be multiplexed"},"\/foobar":{"status":404,"response":"uri did not match a route for path: \/foobar"}}}', $response->getContent());

        //with error messages off
        $this->manager->setConfig(array(
            'restrict_routes' => true,
            'display_errors' => false
        ));

        $response = $this->manager->multiplex($this->request, 'json');
        $this->assertEquals('{"responses":{"":{"status":500,"response":"Internal Server Error"},"\/":{"status":403,"response":"Forbidden"},"\/foobar":{"status":404,"response":"Not Found"}}}', $response->getContent());
    }

    public function testMultiplexWithOneRequest()
    {
        $uri = 'test/uri';
        $match = array('match');
        $sub_content = 'sub content';

        $this->multiplexFixture(array(array('uri' => '/' . $uri, 'method' => null, 'parameters' => null)));

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

        $response = $this->manager->multiplex($this->request);
        $this->assertEquals('{"responses":{"\/test\/uri":{"request":"\/test\/uri","status":200,"response":"sub content"}}}', $response->getContent());
    }

    public function testMultiplexWithMultipleInternalRequests()
    {
        $uriA = 'test/uri';
        $uriB = 'uri/test';

        $match = array('match');
        $sub_contentA = 'sub content A';
        $sub_contentB = 'sub content B';

        $this->multiplexFixture(array(
            array('uri' => '/' . $uriA, 'method' => null, 'parameters' => null),
            array('uri' => '/' . $uriB, 'method' => null, 'parameters' => null)
        ));

        $subResponseA = $this->getMockBuilder('Symfony\Component\HttpFoundation\Response')->disableOriginalConstructor()->getMock();
        $subResponseA->expects($this->once())
            ->method('getContent')
            ->with()
            ->will($this->returnValue($sub_contentA));
        $subResponseA->expects($this->once())
            ->method('isRedirect')
            ->with()
            ->will($this->returnValue(false));

        $subResponseA->expects($this->once())
            ->method('getStatusCode')
            ->with()
            ->will($this->returnValue(200));

        $subResponseB = $this->getMockBuilder('Symfony\Component\HttpFoundation\Response')->disableOriginalConstructor()->getMock();
        $subResponseB->expects($this->once())
            ->method('getContent')
            ->with()
            ->will($this->returnValue($sub_contentB));
        $subResponseB->expects($this->once())
            ->method('isRedirect')
            ->with()
            ->will($this->returnValue(false));

        $subResponseA->expects($this->once())
            ->method('getStatusCode')
            ->with()
            ->will($this->returnValue(200));

        $this->router->expects($this->at(0))
            ->method('match')
            ->with('/' . $uriA)
            ->will($this->returnValue($match));
        $this->router->expects($this->at(1))
            ->method('match')
            ->with('/' . $uriB)
            ->will($this->returnValue($match));

        $this->kernel->expects($this->at(0))
            ->method('handle')
            ->will($this->returnValue($subResponseA));
        $this->kernel->expects($this->at(1))
            ->method('handle')
            ->will($this->returnValue($subResponseB));

        $response = $this->manager->multiplex($this->request);
        $this->assertEquals('{"responses":{"\/test\/uri":{"request":"\/test\/uri","status":200,"response":"sub content A"},"\/uri\/test":{"request":"\/uri\/test","status":null,"response":"sub content B"}}}', $response->getContent());
    }
*/

    private function multiplexFixture($requests = null)
    {
        $this->request->expects($this->atLeastOnce())
            ->method('get')
            ->with('requests')
            ->will($this->returnValue($requests));

        $this->request->expects($this->any())
            ->method('getScriptName')
            ->with()
            ->will($this->returnValue(''));
    }

/*
    public function testMultiplexWithExternalRequests()
    {
        $this->multiplexFixture(array(
            array('uri' => 'http://google.de', 'method' => null, 'parameters' => null),
            array('uri' => 'http://google.com', 'method' => 'POST', 'parameters' => array('q' => 'foo')),
        ));

        $getResponse = new Response();
        $getResponse->setContent('foo');

        $postResponse = new Response();
        $postResponse->setContent('bar');

        $this->browser->expects($this->atLeastOnce())->method('get')->will($this->returnValue($getResponse));
        $this->browser->expects($this->atLeastOnce())->method('submit')->will($this->returnValue($postResponse));

        $response = $this->manager->multiplex($this->request);
        $this->assertEquals('{"responses":{"http:\/\/google.de":{"request":"http:\/\/google.de","status":null,"response":"foo"},"http:\/\/google.com":{"request":"http:\/\/google.com","status":null,"response":"bar"}}}', $response->getContent());
    }

    public function testMultiplexWithUnknownFormatExternalRequests()
    {
        $this->multiplexFixture(array(
            array('uri' => 'http://google.de', 'method' => 'PUT', 'parameters' => null),
        ));

        $response = $this->manager->multiplex($this->request);
        $this->assertEquals('{"responses":{"http:\/\/google.de":{"status":501,"response":"HTTP Method PUT not implemented yet"}}}', $response->getContent());
    }*/
}
