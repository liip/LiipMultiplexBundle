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
    /**
     * handles a single Request
     *
     * @param Request $request
     * @param string $format
     * @param MultiplexDispatcher $dispatcher
     * @return Response
     * @throws HttpExceptionInterface
     */
    public function handleRequest(Request $request, array $requestInfo, MultiplexDispatcher $dispatcher);

    /**
     * checks if this multiplexer can handle this request
     *
     * @param array $requestInfo
     * @return boolean
     */
    public function supports(array $requestInfo);
}
