<?php

namespace Liip\MultiplexBundle\Tests\DependencyInjection;

use Liip\MultiplexBundle\DependencyInjection\LiipMultiplexExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @covers Liip\MultiplexBundle\DependencyInjection\LiipMultiplexExtension
 * @covers Liip\MultiplexBundle\DependencyInjection\Configuration
 */
class MultiplexBundleExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testBoilerplate()
    {
        $extension = new LiipMultiplexExtension();

        $this->assertNotEmpty($extension->getAlias());
    }

    public function testLoadWithDefaults()
    {
        $container = new ContainerBuilder();
        $extension = new LiipMultiplexExtension();

        $extension->load(array(), $container);

        $resources = $container->getResources();
        $this->assertGreaterThan(0, count($resources));

        $expectedServices = array(
            'liip_multiplex_controller',
            'liip_multiplex_handler'
        );

        foreach ($expectedServices as $serviceId) {
            $this->assertTrue($container->hasDefinition($serviceId), $serviceId);
        }

        //test default config
        $calls = $container->getDefinition('liip_multiplex_handler')->getMethodCalls();
        $this->assertGreaterThan(0, count($calls));
        $this->assertContains('setConfig', $calls[0]);
        $this->assertEquals(array(
            'display_errors' => true,
            'restrict_routes' => false,
            'allow_externals' => true,
            'route_option' => 'multiplex_expose'
        ), $calls[0][1][0]);
    }

    public function testLoadWithExternalMultiplexer()
    {
        $container = new ContainerBuilder();
        $extension = new LiipMultiplexExtension();

        $extension->load(array(array(
            'allow_externals' => true
        )), $container);

        $class = $container->getDefinition('liip_multiplex_handler')->getClass();

        $this->assertEquals('Liip\MultiplexBundle\Multiplexer\ExternalRequestMultiplexer', $class);
    }

    public function testXsdValidationBasePath()
    {
        $extension = new LiipMultiplexExtension();

        $this->assertContains('/../Resources/config/schema', $extension->getXsdValidationBasePath());
    }
}
