<?php

namespace Liip\MultiplexBundle\Tests\Multiplexer;

use Buzz\Client\Curl;
use Buzz\Message\Response;
use Liip\MultiplexBundle\Multiplexer\ExternalRequestMultiplexer;

/**
 * @covers Liip\MultiplexBundle\Multiplexer\ExternalRequestMultiplexer
 */
class ExternalRequestMultiplexerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * ExternalRequestMultiplexer
     */
    protected $manager;

    public function setUp()
    {
        $this->request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->disableOriginalConstructor()->getMock();
        $this->kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Kernel')->disableOriginalConstructor()->getMock();
        $this->router = $this->getMockBuilder('Symfony\Component\Routing\RouterInterface')->disableOriginalConstructor()->getMock();
        $this->browser = $this->getMockBuilder('Buzz\Browser')->getMock();
        $this->request->expects($this->any())->method('getRequestFormat')->will($this->returnValue('json'));

        $this->manager = new ExternalRequestMultiplexer($this->kernel, $this->router, $this->browser);
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
