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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * handles an internal requests
 *
 * @author Robert Schönthal <schoenthal.robert_FR@guj.de>
 */
class InternalRequestMultiplexer implements MultiplexerInterface
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
     * the default route option
     */
    private $routeOption = 'multiplex_expose';

    /**
     * by default all routes are multiplexable
     */
    private $restrictRoutes = false;

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
     * enables or disabled the restriction to configured routes
     *
     * @param boolean $state
     * @return MultiplexerInterface
     */
    public function restrictRoutes($state)
    {
        $this->restrictRoutes = $state;

        return $this;
    }

    /**
     * defines the route option to look for restriction
     *
     * @param $option
     * @return MultiplexerInterface
     */
    public function setRouteOption($option)
    {
        $this->routeOption = $option;

        return $this;
    }

    /**
     * {inheritDoc}
     */
    public function handleRequest(Request $request, array $requestInfo, MultiplexDispatcher $dispatcher)
    {
        $subRequest = Request::create($requestInfo['uri'], $requestInfo['method'], $requestInfo['parameters']);
        $subRequest->setSession($request->getSession());

        if (false === ($parameters = $this->router->match($subRequest->getPathInfo()))) {
            throw new NotFoundHttpException('uri did not match a route for path: ' . $subRequest->getPathInfo());
        }

        if (true === $this->restrictRoutes) {
            $route = $this->router->getRouteCollection()->get($parameters['_route']);
            if (!array_key_exists($this->routeOption, $route->getOptions())) {
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

            //feels bad do call the dispatcher again
            return $dispatcher->processRequest($request, $requestInfo);
        }

        return array(
            'request' => $requestInfo['uri'],
            'status' => $response->getStatusCode(),
            'response' => $response->getContent(),
        );
    }

    /**
     * {inheritDoc}
     */
    function supports(array $requestInfo)
    {
        return strpos($requestInfo['uri'], '/') === 0;
    }
}
