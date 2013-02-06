<?php

namespace Liip\MultiplexBundle\Tests\Multiplexer;

use Liip\MultiplexBundle\Multiplexer\MultiplexDispatcher;
use Liip\MultiplexBundle\Multiplexer\InternalRequestMultiplexer;
use Liip\MultiplexBundle\Multiplexer\ExternalRequestMultiplexer;
use Buzz\Message\Response;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\HttpKernel\Kernel;

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

        //see https://github.com/symfony/symfony/commit/2d07a17cbd839b52e547cb80e148f810795c23a1
        if (-1 == version_compare(Kernel::VERSION, '2.2', '<=')) {
            $this->assertEquals('{}', $response->getContent());
        } else {
            $this->assertEquals('[]', $response->getContent());
        }
    }

    public function testMultiplexWithJson()
    {
        $response = $this->manager->multiplex($this->request, 'json');

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);

        //see https://github.com/symfony/symfony/commit/2d07a17cbd839b52e547cb80e148f810795c23a1
        if (-1 == version_compare(Kernel::VERSION, '2.2', '<=')) {
            $this->assertEquals('{}', $response->getContent());
        } else {
            $this->assertEquals('[]', $response->getContent());
        }
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
        $router = $this->getMockBuilder('Symfony\Component\Routing\Router')->disableOriginalConstructor()->getMock();
        $router->expects($this->atLeastOnce())->method('match')->with('/foobar')->will($this->returnValue(array('_route'=>'foo')));

        $context = new RequestContext();
        $context->fromRequest($this->request);
        $router->expects($this->atLeastOnce())->method('getContext')->will($this->returnValue($context));

        $subResponse = $this->getMockBuilder('Symfony\Component\HttpFoundation\Response')->disableOriginalConstructor()->getMock();
        $subResponse->expects($this->once())->method('getContent')->with()->will($this->returnValue('foo'));

        $kernel->expects($this->once())->method('handle')->will($this->returnValue($subResponse));

        $this->manager->addMultiplexer(new InternalRequestMultiplexer($kernel, $router));

        $response = $this->manager->multiplex($this->request);
        $this->assertEquals('{"":{"request":"","status":500,"response":"no uri given"},"http:\/\/google.com":{"request":"http:\/\/google.com","status":null,"response":"bar"},"\/foobar":{"request":"\/foobar","status":null,"response":"foo"}}', $response->getContent());
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
        $router = $this->getMockBuilder('Symfony\Component\Routing\Router')->disableOriginalConstructor()->getMock();
        $router->expects($this->atLeastOnce())->method('match')->with('/foobar')->will($this->returnValue(false));

        $context = new RequestContext();
        $context->fromRequest($this->request);
        $router->expects($this->atLeastOnce())->method('getContext')->will($this->returnValue($context));

        $this->manager->addMultiplexer(new InternalRequestMultiplexer($kernel, $router));
        $this->manager->displayErrors(false);

        $response = $this->manager->multiplex($this->request);
        $this->assertEquals('{"":{"request":"","status":500,"response":"Internal Server Error"},"http:\/\/google.com":{"request":"http:\/\/google.com","status":501,"response":"Not Implemented"},"\/foobar":{"request":"\/foobar","status":404,"response":"Not Found"}}', $response->getContent());
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
    }
}
