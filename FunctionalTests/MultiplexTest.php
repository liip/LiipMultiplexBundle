<?php

namespace Liip\MultiplexBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MultiplexTest extends WebTestCase
{
    public function testIndex()
    {
        $client = $this->createClient(array('environment' => 'test'));
        $client->request('GET', '/multiplex.json?requests[0][uri]=/');

        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function testIndexIncorrectFormat()
    {
        $client = $this->createClient(array('environment' => 'test'));
        $client->request('GET', '/multiplex.foo');

        $this->assertFalse($client->getResponse()->isSuccessful());
    }
}
