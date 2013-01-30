<?php
/**
 * This file is part of the Liip/MultiplexBundle
 *
 * (c) Lukas Kahwe Smith <smith@pooteeweet.org>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Liip\MultiplexBundle\Multiplexer;

use Symfony\Component\HttpFoundation\Request;

/**
 * Interface for all Multiplexers
 *
 * @author Robert Schönthal <schoenthal.robert_FR@guj.de>
 */
interface MultiplexerInterface
{
    function multiplex(Request $request, $format = null);
}
