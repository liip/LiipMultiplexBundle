<?php

namespace Liip\MultiplexBundle\Tests\Controller;

use Liip\MultiplexBundle\Controller\MultiplexController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers Liip\MultiplexBundle\Controller\MultiplexController
 */
class MultiplexControllerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->dispatcher = $this->getMockBuilder('Liip\MultiplexBundle\Multiplexer\MultiplexDispatcher')
            ->setMethods(array('multiplex'))
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testConstructor()
    {
        $controller = new MultiplexController($this->dispatcher);

        $this->assertAttributeSame($this->dispatcher, 'dispatcher', $controller);
    }

    public function testIndexActionWithJson()
    {
        $controller = new MultiplexController($this->dispatcher);
        $request = new Request();
        $request->setRequestFormat('json');

        if (class_exists('Symfony\Component\HttpFoundation\JsonResponse')) {
            $this->dispatcher->expects($this->atLeastOnce())->method('multiplex')->will($this->returnValue(new JsonResponse(array('foo' => 'bar'))));
        } else {
            $this->dispatcher->expects($this->atLeastOnce())->method('multiplex')->will($this->returnValue(new Response(json_encode(array('foo' => 'bar')))));
        }

        $response = $controller->indexAction($request);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
        $this->assertEquals('{"foo":"bar"}', $response->getContent());
    }

    public function testIndexActionWithHtml()
    {
        $controller = new MultiplexController($this->dispatcher);
        $request = new Request();
        $request->setRequestFormat('html');

        $this->dispatcher->expects($this->atLeastOnce())->method('multiplex')->will($this->returnValue(new Response('<pre>foo</pre>')));

        $response = $controller->indexAction($request);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
        $this->assertEquals('<pre>foo</pre>', $response->getContent());
    }

}
