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

        //configure the dispatcher
        if ($container->hasDefinition('liip_multiplex.dispatcher')) {
            $container->getDefinition('liip_multiplex.dispatcher')->addMethodCall('displayErrors', array($processedConfig['display_errors']));
        }

        //set config vars for internal request multiplexer
        if ($container->hasDefinition('liip_multiplex.multiplexer.internal_requests')) {
            $container->getDefinition('liip_multiplex.multiplexer.internal_requests')->addMethodCall('restrictRoutes', array($processedConfig['restrict_routes']));
            $container->getDefinition('liip_multiplex.multiplexer.internal_requests')->addMethodCall('setRouteOption', array($processedConfig['route_option']));
        }

        //set config vars for external request multiplexer
        if ($container->hasDefinition('liip_multiplex.multiplexer.external_requests')) {
            if (false == $processedConfig['allow_externals']) {
                $container->removeDefinition('liip_multiplex.multiplexer.external_requests');
                $container->removeDefinition('liip_multiplex.buzz');
                $container->removeDefinition('liip_multiplex.buzz.message_factory');
                $container->removeDefinition('liip_multiplex.buzz.client');
            } else {
                $buzz = $this->checkForBuzzDependency($container);

                $container->getDefinition('liip_multiplex.multiplexer.external_requests')->replaceArgument(0, $buzz);
            }
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

            throw new ServiceNotFoundException('buzz', 'liip_multiplex.multiplexer.external_requests');
        }

        return $container->hasDefinition('buzz') ? $container->getDefinition('buzz') : $container->getDefinition('liip_multiplex.multiplexer.external_requests');
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
