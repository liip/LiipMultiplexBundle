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
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
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

        $this->setHandlerConfig($container, $processedConfig);

        //switch to the external multiplexer if external requests are enabled
        if (true == $processedConfig['allow_externals']) {
            $this->activateExternalRequests($container);
        }
    }

    /**
     * inject the handler config
     *
     * @param ContainerBuilder $container
     * @param array $processedConfig
     */
    private function setHandlerConfig(ContainerBuilder $container, array $processedConfig)
    {
        if ($container->hasDefinition('liip_multiplex_handler')) {
            $container->getDefinition('liip_multiplex_handler')->addMethodCall('setConfig', array($processedConfig));
        }
    }

    /**
     * switch the handler class to enable external request multiplexing if possible (buzz must be enabled)
     *
     * @param ContainerBuilder $container
     */
    private function activateExternalRequests(ContainerBuilder $container)
    {
        if ($container->hasDefinition('liip_multiplex_handler')) {
            $buzz = $this->checkForBuzzDependency($container);

            $container->getDefinition('liip_multiplex_handler')->setClass($container->getParameter('liip_multiplex.external_multiplexer.class'));
            $container->getDefinition('liip_multiplex_handler')->addArgument($buzz);
        }
    }

    /**
     * check if its possible to get a buzz service from the DIC
     *  if "buzz" is available this service will be used
     *  if not a own buzz service will be constructed
     *
     * @param ContainerBuilder $container
     * @return Definition
     * @throws ServiceNotFoundException if no buzz Service could be created
     */
    private function checkForBuzzDependency(ContainerBuilder $container)
    {
        if (!class_exists($container->getParameter('liip_multiplex_buzz.browser.class'))) {
            throw new ServiceNotFoundException('buzz', 'liip_multiplex_handler');
        }

        return $container->hasDefinition('buzz') ? $container->getDefinition('buzz') : $container->getDefinition('liip_multiplex_buzz');
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
