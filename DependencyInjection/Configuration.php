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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 *
 * @author Robert Schönthal <robert.schoenthal@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('liip_multiplex');

        $rootNode
            ->children()
                ->booleanNode('display_errors')->defaultTrue()->end()
                ->booleanNode('restrict_routes')->defaultFalse()->end()
                ->booleanNode('allow_externals')->defaultTrue()->end()
                ->scalarNode('route_option')->defaultValue('multiplex_expose')->end()
            ->end();

        return $treeBuilder;
    }
}
