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
class MultiplexManager
{
    /**
     * @var HttpKernelInterface
     */
    protected $kernel;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var array
     */
    protected $config = array(
        'display_errors' => true,
        'route_option' => 'multiplex_expose',
        'restrict_routes' => false,
    );

    /**
     * Constructor
     *
     * @param HttpKernelInterface $kernel the Symfony Kernel
     * @param RouterInterface $router the Symfony Router
     */
    public function __construct(HttpKernelInterface $kernel, RouterInterface $router)
    {
        $this->kernel = $kernel;
        $this->router = $router;
    }

    /**
     * set the config
     *
     * @param array $config the config
     *                          display_errors = show exception error messages
     *                          route_option = the route option to be used for restriction checks
     *                          restrict_routes = restrict calling of routes on the ones with the route-option
     * @return MultiplexManager
     */
    public function setConfig(array $config)
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * starts the multiplexing of sub-requests
     *
     * @param Request $request the Symfony Request
     * @param string $format the format to return (html|json)
     * @return Response the combined responses
     */
    public function multiplex(Request $request, $format = null)
    {
        $format = $format ?: $request->getRequestFormat();

        $requests = (array) $request->get('requests', array());
        $responses = $this->processRequests($request, $requests);

        return $this->buildResponse($responses, $format);
    }

    /**
     * processes all requests
     *
     * @param Request $request the Symfony Request
     * @param array $requests the requests to process
     * @return array the responses
     */
    protected function processRequests(Request $request, array $requests)
    {
        $responses = array('responses' => array());

        foreach ($requests as $i => $requestInfo) {
            try {
                $requestInfo = $this->sanitizeRequest($request, $requestInfo, $i);
                $response = $this->handleRequest($request, $requestInfo, $i);
                $responses['responses'][$response['request']] = $response;
            } catch (\Exception $e) {
                $code = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

                $message = $this->config['display_errors'] ? $e->getMessage() : Response::$statusTexts[$code];
                $responses['responses'][$requestInfo['uri']] = array('status' => $code, 'response' => $message);
            }
        }

        return $responses;
    }

    /**
     * builds the response based on the format and single responses
     *
     * @param array $responses the single responses
     * @param string $format the output format
     * @return Response a Symfony2 Response
     */
    protected function buildResponse(array $responses, $format)
    {
        switch ($format) {
            case 'json' : return new JsonResponse($responses);
            case 'html' : return new Response('<pre>' . var_export($responses, true) . '</pre>');
        }

        throw new HttpException(501, 'Response format '.$format.' not implemented yet');
    }

    /**
     * Handle a single request
     *
     * @param Request $request the Symfony Request
     * @param array $requestInfo array contains 'uri', 'method' and 'query'
     * @param int $i the current request index
     * @return array contains 'request', 'status' and 'response'
     * @throws HttpExceptionInterface if the uri cant be matched by the router
     */
    protected function handleRequest(Request $request, array $requestInfo, $i)
    {
        $subRequest = Request::create($requestInfo['uri'], $requestInfo['method'], $requestInfo['parameters']);
        $subRequest->setSession($request->getSession());

        if (false === ($parameters = $this->router->match($subRequest->getPathInfo()))) {
            throw new NotFoundHttpException('uri did not match a route for path: ' . $subRequest->getPathInfo());
        }

        if (true === $this->config['restrict_routes']) {
            $route = $this->router->getRouteCollection()->get($parameters['_route']);
            if (!array_key_exists($this->config['route_option'], $route->getOptions())) {
                throw new AccessDeniedHttpException('route not able to be multiplexed');
            }
        }

        $subRequest->attributes->add($parameters);
        $response = $this->kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);

        if ($response->isRedirect()) {
            $request = array(
                'uri' => $response->headers->get('location'),
                'method' => 'GET',
                'parameters' => array(),
            );

            return $this->handleRequest($request, $requestInfo, $i);
        }

        return array(
            'request' => $requestInfo['uri'],
            'status' => $response->getStatusCode(),
            'response' => $response->getContent(),
        );
    }

    /**
     * @param Request $request the Symfony Request
     * @param array $requestInfo array contains 'uri', 'method' and 'query'
     * @param int $i the request index
     * @return array the sanitized request
     * @throws \InvalidArgumentException if the single request is invalid
     */
    private function sanitizeRequest(Request $request, array $requestInfo, $i)
    {
        if (empty($requestInfo['uri'])) {
            throw new \InvalidArgumentException('no uri given for index: ' . $i);
        }

        $requestInfo['uri'] = preg_replace('/^(' . preg_quote($request->getScriptName(), '/') . ')?/', '', $requestInfo['uri']);

        $requestInfo['method'] = empty($requestInfo['method']) ? 'GET' : strtoupper($requestInfo['method']);

        if (empty($requestInfo['parameters'])) {
            $requestInfo['parameters'] = array();
        }

        return $requestInfo;
    }
}
