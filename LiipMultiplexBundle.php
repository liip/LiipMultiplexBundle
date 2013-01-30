<?php
/**
 * This file is part of the Liip/MultiplexBundle
 *
 * (c) Lukas Kahwe Smith <smith@pooteeweet.org>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Liip\MultiplexBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Liip\MultiplexBundle\DependencyInjection\CompilerPass\AddMultiplexerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * default bundle
 */
class LiipMultiplexBundle extends Bundle
{
    /**
     * {inheritDoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new AddMultiplexerPass());
    }

}