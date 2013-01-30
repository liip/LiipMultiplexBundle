<?php

/*
 * This file is part of the Liip/MultiplexBundle
 *
 * (c) Lukas Kahwe Smith <smith@pooteeweet.org>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Liip\MultiplexBundle\Controller;

use Liip\MultiplexBundle\Manager\MultiplexManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MultiplexController
{
    private $manager;

    public function __construct(MultiplexManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Handle the the multiplexing
     *
     * @param Request $request the Symfony Request
     * @return Response
     */
    public function indexAction(Request $request)
    {
        return $this->manager->multiplex($request);
    }
}
