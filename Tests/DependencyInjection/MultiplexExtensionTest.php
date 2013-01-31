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
            'liip_multiplex.controller',
            'liip_multiplex.dispatcher',
            'liip_multiplex.multiplexer.internal_requests',
            'liip_multiplex.multiplexer.external_requests'
        );

        foreach ($expectedServices as $serviceId) {
            $this->assertTrue($container->hasDefinition($serviceId), $serviceId);
        }
    }

    public function testLoadWithoutExternals()
    {
        $container = new ContainerBuilder();
        $extension = new LiipMultiplexExtension();

        $extension->load(array(array('allow_externals' => false)), $container);

        $this->assertFalse($container->hasDefinition('liip_multiplex.multiplexer.external_requests'));
    }

    public function testXsdValidationBasePath()
    {
        $extension = new LiipMultiplexExtension();

        $this->assertContains('/../Resources/config/schema', $extension->getXsdValidationBasePath());
    }
}
