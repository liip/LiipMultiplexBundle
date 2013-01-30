<?php

/*
 * This file is part of the Liip/MultiplexBundle
 *
 * (c) Lukas Kahwe Smith <smith@pooteeweet.org>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Liip\MultiplexBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class LiipMultiplexExtension extends Extension
{

    /**
     * Loads the services based on your application configuration.
     *
     * @param array $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('multiplex.xml');

        $processedConfig = $this->processConfiguration(new Configuration(), $configs);

        if ($container->hasDefinition('liip_multiplex_handler')) {
            $container->getDefinition('liip_multiplex_handler')->addMethodCall('setConfig', array($processedConfig));
        }

        //switch to the external multiplexer if external requests are enabled
        if (true == $processedConfig['allow_externals'] && $container->hasDefinition('liip_multiplex_handler')) {
            $buzz = $container->hasDefinition('buzz') ? $container->getDefinition('buzz') : $container->getDefinition('liip_multiplex_buzz');

            $container->getDefinition('liip_multiplex_handler')->setClass($container->getParameter('liip_multiplex.external_multiplexer.class'));
            $container->getDefinition('liip_multiplex_handler')->addArgument($buzz);
        }
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__ . '/../Resources/config/schema';
    }
}
