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
    protected $multiplexer;

    public function setUp()
    {
        $this->browser = $this->getMockBuilder('Buzz\Browser')->getMock();
        $this->request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->disableOriginalConstructor()->getMock();

        $this->multiplexer = new ExternalRequestMultiplexer($this->browser);
        $this->dispatcher = $this->getMock('Liip\MultiplexBundle\Multiplexer\MultiplexDispatcher');
    }

    public function testSupports()
    {
        $this->assertTrue($this->multiplexer->supports(array('uri' => 'http://google.com')));
        $this->assertTrue($this->multiplexer->supports(array('uri' => 'https://google.com')));
        $this->assertFalse($this->multiplexer->supports(array('uri' => '/')));
    }

    public function testHandleGetRequest()
    {
        $request = array('uri' => 'http://google.de', 'method' => 'GET', 'parameters' => array());

        $getResponse = new Response();
        $getResponse->setContent('foo');

        $this->browser->expects($this->atLeastOnce())->method('get')->will($this->returnValue($getResponse));

        $response = $this->multiplexer->handleRequest($this->request, $request, $this->dispatcher);
        $this->assertEquals(array('request'=>'http://google.de', 'status'=>null, 'response' => 'foo'), $response);
    }

    public function testHandlePostRequest()
    {
        $request = array('uri' => 'http://google.com', 'method' => 'POST', 'parameters' => array('q' => 'foo'));

        $postResponse = new Response();
        $postResponse->setContent('bar');

        $this->browser->expects($this->atLeastOnce())->method('submit')->will($this->returnValue($postResponse));

        $response = $this->multiplexer->handleRequest($this->request, $request, $this->dispatcher);
        $this->assertEquals(array('request'=>'http://google.com', 'status'=>null, 'response' => 'bar'), $response);
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function testHandleUnknownRequestFormat()
    {
        $request = array('uri' => 'http://google.de', 'method' => 'PUT', 'parameters' => null);

        $this->multiplexer->handleRequest($this->request, $request, $this->dispatcher);
    }
}
