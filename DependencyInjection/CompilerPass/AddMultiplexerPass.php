<?php
/**
 * This file is part of the Liip/MultiplexBundle
 *
 * (c) Lukas Kahwe Smith <smith@pooteeweet.org>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Liip\MultiplexBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AddMultiplexerPass implements CompilerPassInterface
{
    /**
     * {inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('liip_multiplex.dispatcher')) {
            return;
        }

        $multiplexers = $container->findTaggedServiceIds('liip_multiplexer');

        foreach ($multiplexers as $id => $multiplexer) {
            $container->getDefinition('liip_multiplex.dispatcher')->addMethodCall('addMultiplexer', array(new Reference($id)));
        }
    }
}
