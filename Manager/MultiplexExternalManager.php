<?php

/*
 * This file is part of the Liip/MultiplexBundle
 *
 * (c) Lukas Kahwe Smith <smith@pooteeweet.org>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Liip\MultiplexBundle\Manager;

use Buzz\Browser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Matcher\TraceableUrlMatcher;
use Symfony\Component\Routing\RouterInterface;

/**
 * Multiplexes a bunch of Requests, and returns one Response
 *
 * @author Robert Schönthal <robert.schoenthal@gmail.com>
 */
class MultiplexExternalManager extends MultiplexManager
{
    /**
     * @var Browser
     */
    protected $browser;

    /**
     * Constructor
     *
     * @param HttpKernelInterface $kernel the Symfony Kernel
     * @param RouterInterface $router the Symfony Router
     * @param Browser $browser the Buzz Browser
     */
    public function __construct(HttpKernelInterface $kernel, RouterInterface $router, Browser $browser)
    {
        parent::__construct($kernel, $router);

        $this->browser = $browser;
    }

    /**
     * Handle a single request
     *
     * @param Request $request the Symfony Request
     * @param array $requestInfo array contains 'uri', 'method' and 'query'
     * @param int $i the current request index
     * @return array contains 'request', 'status' and 'response'
     * @throws HttpExceptionInterface if external calls are disabled
     */
    protected function handleRequest(Request $request, array $requestInfo, $i)
    {
        if ($this->isInternalRequest($requestInfo)) {
            //handle internal requests
            return parent::handleRequest($request, $requestInfo, $i);
        }

        switch ($requestInfo['method']) {
            case 'GET' :
                $delimiter = strpos($requestInfo['uri'], '?') === false ? '?' : '&';
                $response = $this->browser->get($requestInfo['uri'] . $delimiter . http_build_query($requestInfo['parameters']));
                break;
            case 'POST' :
                $response = $this->browser->submit(
                    $requestInfo['uri'],
                    $requestInfo['parameters'],
                    'POST'
                );
                break;
            default:
                throw new HttpException(501, 'HTTP Method '.$requestInfo['method'].' not implemented yet');
        }

        return array(
            'request' => $requestInfo['uri'],
            'status' => $response->getStatusCode(),
            'response' => $response->getContent(),
        );
    }

    /**
     * checks if the subRequest is an internal Request which could be handled by our application
     *
     * @param array $requestInfo array contains 'uri', 'method' and 'query'
     * @return boolean if the request can be handled by our application
     */
    private function isInternalRequest(array $requestInfo)
    {
        return strpos($requestInfo['uri'], '/') === 0;
    }
}
