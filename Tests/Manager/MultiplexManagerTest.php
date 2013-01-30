<?php

namespace Liip\MultiplexBundle\Tests\Manager;

use Buzz\Client\Curl;
use Buzz\Message\Response;
use Liip\MultiplexBundle\Manager\MultiplexManager;

/**
 * @covers Liip\MultiplexBundle\Manager\MultiplexManager
 */
class MultiplexManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * MultiplexManager
     */
    protected $manager;

    public function setUp()
    {
        $this->request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->disableOriginalConstructor()->getMock();
        $this->kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Kernel')->disableOriginalConstructor()->getMock();
        $this->router = $this->getMockBuilder('Symfony\Component\Routing\RouterInterface')->disableOriginalConstructor()->getMock();
        $this->browser = $this->getMockBuilder('Buzz\Browser')->getMock();
        $this->request->expects($this->any())->method('getRequestFormat')->will($this->returnValue('json'));

        $this->manager = new MultiplexManager($this->kernel, $this->router, $this->browser);
    }

    public function testConstructor()
    {
        $this->assertAttributeSame($this->kernel, 'kernel', $this->manager);
        $this->assertAttributeSame($this->router, 'router', $this->manager);
    }

    public function testMultiplexWithDefaults()
    {
        $response = $this->manager->multiplex($this->request);

        $this->assertEquals('{"responses":[]}', $response->getContent());
    }

    public function testMultiplexWithJson()
    {
        $response = $this->manager->multiplex($this->request, 'json');

        $this->assertEquals('{"responses":[]}', $response->getContent());
    }

    public function testMultiplexWithHtml()
    {
        $response = $this->manager->multiplex($this->request, 'html');

        $this->assertEquals("<pre>array (\n  'responses' => \n  array (\n  ),\n)</pre>", $response->getContent());
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
     */
    public function testMultiplexWithUnknownFormat()
    {
        $response = $this->manager->multiplex($this->request, 'yml');
    }

    public function testMultiplexWithFaultyParameters()
    {
        $this->multiplexFixture(array(
            array('uri' => ''),
            array('uri' => 'http://google.com'),
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
            'allow_externals' => false,
            'restrict_routes' => true,
            'display_errors' => true
        ));

        $response = $this->manager->multiplex($this->request, 'json');
        $this->assertEquals('{"responses":{"":{"status":500,"response":"no uri given for index: 0"},"http:\/\/google.com":{"status":400,"response":"external calls are not enabled"},"\/":{"status":403,"response":"route not able to be multiplexed"},"\/foobar":{"status":404,"response":"uri did not match a route for path: \/foobar"}}}', $response->getContent());

        //with error messages off
        $this->manager->setConfig(array(
            'allow_externals' => false,
            'restrict_routes' => true,
            'display_errors' => false
        ));

        $response = $this->manager->multiplex($this->request, 'json');
        $this->assertEquals('{"responses":{"":{"status":500,"response":"Internal Server Error"},"http:\/\/google.com":{"status":400,"response":"Bad Request"},"\/":{"status":403,"response":"Forbidden"},"\/foobar":{"status":404,"response":"Not Found"}}}', $response->getContent());
    }

    public function testMultiplexWithOneInternalRequest()
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

        $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\Session')->disableOriginalConstructor()->getMock();
        $this->request->expects($this->any())
            ->method('getSession')
            ->with()
            ->will($this->returnValue($session));
    }

}
