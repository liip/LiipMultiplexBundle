<?php

namespace Bundle\Liip\MultiplexBundle\Tests\Controller;

use Bundle\Liip\MultiplexBundle\Controller\MultiplexController;

class MultiplexControllerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->disableOriginalConstructor()->getMock();

        $this->response = $this->getMockBuilder('Symfony\Component\HttpFoundation\Response')->disableOriginalConstructor()->getMock();
        $this->headers = $this->getMockBuilder('Symfony\Component\HttpFoundation\HeaderBag')->disableOriginalConstructor()->getMock();
        $this->response->headers = $this->headers;

        $this->kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Kernel')->disableOriginalConstructor()->getMock();

        $this->router = $this->getMockBuilder('Symfony\Component\Routing\Router')->disableOriginalConstructor()->getMock();
    }

    /**
     * @covers Bundle\Liip\MultiplexBundle\Controller\MultiplexController::__construct
     */
    public function testConstructor() {
        $controller = new MultiplexController('request', 'response', 'kernel', 'router');
        $this->assertAttributeSame(array('params'), 'params', $controller);
        $this->assertAttributeSame('request', 'request', $controller);
        $this->assertAttributeSame('response', 'response', $controller);
        $this->assertAttributeSame('kernel', 'kernel', $controller);
        $this->assertAttributeSame('router', 'router', $controller);
    }

    /**
     * Fixture for testing different format outputs
     *
     * @param  string $mimeType expected mime type like 'text/html'
     * @param  string $content expected output content for the given mime type
     * @return void
     */
    public function indexActionFixture($mimeType, $content)
    {
        $this->response->expects($this->once())
            ->method('setStatusCode')
            ->with('200')
            ->will($this->returnValue(null))
        ;

        $this->request->expects($this->once())
            ->method('get')
            ->with('requests')
            ->will($this->returnValue(null))
        ;

        $this->response->expects($this->once())
            ->method('setContent')
            ->with($content)
            ->will($this->returnValue(null))
        ;
    }

    /**
     * @covers Bundle\Liip\MultiplexBundle\Controller\MultiplexController::indexAction
     */
    public function testIndexActionWithJson()
    {
        $this->indexActionFixture('application/json', '{"response":[],"status":"success"}');

        $controller = new MultiplexController($this->request, $this->response, $this->kernel, $this->router);
        $controller->indexAction('json');
    }

    /**
     * @covers Bundle\Liip\MultiplexBundle\Controller\MultiplexController::indexAction
     */
    public function testIndexActionWithDefault()
    {
        $this->indexActionFixture('text/html', "<pre>array (\n  'response' => \n  array (\n  ),\n  'status' => 'success',\n)</pre>");

        $controller = new MultiplexController($this->request, $this->response, $this->kernel, $this->router);
        $controller->indexAction('XXX');
    }

    /**
     * @covers Bundle\Liip\MultiplexBundle\Controller\MultiplexController::indexAction
     */
    public function testIndexActionWithHtml()
    {
        $this->indexActionFixture('text/html', "<pre>array (\n  'response' => \n  array (\n  ),\n  'status' => 'success',\n)</pre>");

        $controller = new MultiplexController($this->request, $this->response, $this->kernel, $this->router);
        $controller->indexAction('html');
    }

    /**
     * @covers Bundle\Liip\MultiplexBundle\Controller\MultiplexController::indexAction
     */
    public function testIndexActionFaultyParameter()
    {
        $this->response->expects($this->once())
            ->method('setStatusCode')
            ->with('200')
            ->will($this->returnValue(null))
        ;

        $this->request->expects($this->once())
            ->method('get')
            ->with('requests')
            ->will($this->returnValue(array(array('controller' => ''))))
        ;

        $this->response->expects($this->once())
            ->method('setContent')
            ->with('{"response":[{"id":0,"status":"500","html":"no uri given for index: 0"}],"status":"success"}')
            ->will($this->returnValue(null))
        ;

        $controller = new MultiplexController($this->request, $this->response, $this->kernel, $this->router);
        $controller->indexAction('json');
    }

    /**
     * @covers Bundle\Liip\MultiplexBundle\Controller\MultiplexController::indexAction
     * @covers Bundle\Liip\MultiplexBundle\Controller\MultiplexController::handleRequest
     */
    public function testIndexActionWithParameter()
    {
        $uri = 'test/uri';
        $pathinfo = 'pathinfo';
        $match = array('match');
        $sub_content = 'sub content';

        $this->response->expects($this->once())
            ->method('setStatusCode')
            ->with('200')
            ->will($this->returnValue(null))
        ;

        $this->request->expects($this->once())
            ->method('get')
            ->with('requests')
            ->will($this->returnValue(array(array('uri' => '/'.$uri, 'method' => null, 'parameters' => null))))
        ;
        $this->request->expects($this->once())
            ->method('getScriptName')
            ->with()
            ->will($this->returnValue(''))
        ;
        $subRequest = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->disableOriginalConstructor()->getMock();
        $subRequest->expects($this->once())
            ->method('getPathInfo')
            ->with()
            ->will($this->returnValue($pathinfo))
        ;
        $attributes = $this->getMockBuilder('\Symfony\Component\HttpFoundation\ParameterBag')->disableOriginalConstructor()->getMock();
        $attributes->expects($this->once())
            ->method('add')
            ->with($match)
            ->will($this->returnValue(null))
        ;
        $subRequest->attributes = $attributes;
        $this->request->expects($this->once())
            ->method('create')
            ->with($uri, 'get', array())
            ->will($this->returnValue($subRequest))
        ;

        $this->response->expects($this->once())
            ->method('setContent')
            ->with('{"response":["'.$sub_content.'"],"status":"success"}')
            ->will($this->returnValue(null))
        ;

        $subResponse = $this->getMockBuilder('Symfony\Component\HttpFoundation\Response')->disableOriginalConstructor()->getMock();
        $subResponse->expects($this->once())
            ->method('getContent')
            ->with()
            ->will($this->returnValue(json_encode(array('response' => array($sub_content)))))
        ;

        $this->router->expects($this->once())
            ->method('match')
            ->with($pathinfo)
            ->will($this->returnValue($match))
        ;

        $this->kernel->expects($this->once())
            ->method('handle')
            ->with($subRequest, \Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST)
            ->will($this->returnValue($subResponse))
        ;

        $controller = new MultiplexController($this->request, $this->response, $this->kernel, $this->router);
        $controller->indexAction('json');
    }

}
