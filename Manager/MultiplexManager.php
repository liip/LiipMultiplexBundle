<?php
/**
 * @version $Id: MultiplexManager.php 1373 2013-01-25 16:27:00Z digitalkaoz $
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
     * @var Request
     */
    protected $request;

    /**
     * @var HttpKernelInterface
     */
    protected $kernel;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var Browser
     */
    protected $browser;

    /**
     * @var array
     */
    protected $config = array(
        'display_errors' => true,
        'route_option' => 'multiplex_expose',
        'restrict_routes' => false,
        'allow_externals' => true
    );

    /**
     * Constructor
     *
     * @param HttpKernelInterface $kernel the Symfony Kernel
     * @param RouterInterface $router the Symfony Router
     * @param Browser $browser the Buzz Browser
     */
    public function __construct(HttpKernelInterface $kernel, RouterInterface $router, Browser $browser)
    {
        $this->kernel = $kernel;
        $this->router = $router;
        $this->browser = $browser;
    }

    /**
     * set the config
     *
     * @param array $config the config
     *                          display_errors = show exception error messages
     *                          route_option = the route option to be used for restriction checks
     *                          restrict_routes = restrict calling of routes on the ones with the route-option
     *                          allow_externals = if external requests should be possible
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
        $this->request = $request;
        $format = $format ?: $request->getRequestFormat();

        $requests = $this->request->get('requests');
        $responses = $this->processRequests($requests ? $requests : array());

        return $this->buildResponse($responses, $format);
    }

    /**
     * processes all requests
     *
     * @param array $requests the requests to process
     * @return array the responses
     */
    protected function processRequests(array $requests)
    {
        $responses = array('responses' => array());

        foreach ($requests as $i => $request) {
            try {
                $response = $this->handleRequest($request, $i);
                $responses['responses'][$response['request']] = $response;
            } catch (\Exception $e) {
                $code = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

                if (true === $this->config['display_errors']) {
                    $message = $e->getMessage();
                } else {
                    $message = Response::$statusTexts[$code];
                }
                $responses['responses'][$request['uri']] = array('status' => $code, 'response' => $message);
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
        if ('json' == $format) {
            return new JsonResponse($responses);
        } elseif ('html' == $format) {
            return new Response('<pre>' . var_export($responses, true) . '</pre>');
        }

        throw new HttpException(501, 'Response format '.$format.' not implemented yet');
    }

    /**
     * Handle a single request
     *
     * @param array $request array contains 'uri', 'method' and 'query'
     * @param int $i the current request index
     * @return array contains 'request', 'status' and 'response'
     * @throws HttpExceptionInterface if external calls are disabled
     */
    protected function handleRequest(array $request, $i)
    {
        $request = $this->sanitizeRequest($request, $i);

        if ($this->isInternalRequest($request)) {
            //handle internal requests
            return $this->handleInternalRequest($request, $i);
        }

        if (false === $this->config['allow_externals']) {
            throw new HttpException(400, 'external calls are not enabled');
        }
        //handle external requests with buzz
        return $this->handleExternalRequest($request, $i);
    }

    /**
     * handles an internal request (by the application itself)
     *
     * @param array $request the request object
     * @param int $i the request index
     * @return array the response array
     * @throws HttpExceptionInterface if the uri cant be matched by the router
     */
    protected function handleInternalRequest(array $request, $i)
    {
        $request['uri'] = $this->prepareRequestUri($request);
        $subRequest = Request::create($request['uri'], $request['method'], $request['parameters']);
        $subRequest->setSession($this->request->getSession());

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
                'method' => 'get',
                'parameters' => array(),
            );
            return $this->handleRequest($request, $i);
        }

        return array(
            'request' => $request['uri'],
            'status' => $response->getStatusCode(),
            'response' => $response->getContent(),
        );
    }

    /**
     * handles an external request with buzz
     *
     * @param array $request the request object
     * @param int $i the request index
     * @return array the response array
     */
    protected function handleExternalRequest(array $request, $i)
    {
        if ('GET' == $request['method']) {
            $delimiter = strpos($request['uri'], '?') === false ? '?' : '&';
            $response = $this->browser->get($request['uri'] . $delimiter . http_build_query($request['parameters']));
        } elseif ('POST' == $request['method']) {

            $response = $this->browser->submit(
                $request['uri'],
                $request['parameters'],
                'POST'
            );
        } else {
            throw new HttpException(501, 'HTTP Method '.$request['method'].' not implemented yet');
        }

        return array(
            'request' => $request['uri'],
            'status' => $response->getStatusCode(),
            'response' => $response->getContent(),
        );
    }

    /**
     * @param array $request the request object
     * @param int $i the request index
     * @return array the sanitized request
     * @throws \InvalidArgumentException if the single request is invalid
     */
    private function sanitizeRequest(array $request, $i)
    {
        if (empty($request['uri'])) {
            throw new \InvalidArgumentException('no uri given for index: ' . $i);
        }

        if (empty($request['method'])) {
            $request['method'] = 'GET';
        }
        if (empty($request['parameters'])) {
            $request['parameters'] = array();
        }

        return $request;
    }

    /**
     * checks if the subRequest is an internal Request which could be handled by our application
     *
     * @param array $request the request
     * @return boolean if the request can be handled by our application
     */
    private function isInternalRequest(array $request)
    {
        return strpos($request['uri'], '/') === 0;
    }

    /**
     * prepares a local url to a path which could be matched by the Router
     *
     * @param array $request the Request Array
     * @return string the prepared uri
     */
    private function prepareRequestUri(array $request)
    {
        return preg_replace('/^(' . preg_quote($this->request->getScriptName(), '/') . ')?/', '', $request['uri']);
    }
}
