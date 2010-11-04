<?php

/*
 * This file is part of the Liip/MultiplexBundle
 *
 * (c) Lukas Kahwe Smith <smith@pooteeweet.org>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Bundle\Liip\MultiplexBundle\Controller;

class MultiplexController
{
    /**
     * Request
     * @var Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * Response
     * @var Symfony\Component\HttpFoundation\Response
     */
    protected $response;

    /**
     * Kernel
     * @var Symfony\Component\HttpKernel\Kernel
     */
    protected $kernel;

    /**
     * Router
     * @var Symfony\Component\Routing\RouterInterface
     */
    protected $router;

    /**
     * Constructor
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param Symfony\Component\HttpFoundation\Response $response
     * @param Symfony\Component\HttpKernel\Kernel $kernel
     * @param Symfony\Component\Routing\RouterInterface $router
     */
    public function __construct($request, $response, $kernel, $router)
    {
        $this->request = $request;
        $this->response = $response;
        $this->kernel = $kernel;
        $this->router = $router;
    }

    /**
     * Handle a single request
     *
     * @param array $request array contains 'uri', 'method' and 'query'
     * @param string $id id value
     * @return array contains 'id', 'status' and 'html'
     */
    protected function handleRequest($request, $i)
    {
        if (empty($request['uri'])) {
            throw new \InvalidArgumentException('no uri given for index: '.$i);
        }
        $request['uri'] = preg_replace('/^('.preg_quote($this->request->getScriptName(), '/').')?\//', '', $request['uri']);

        if (empty($request['method'])) {
            $request['method'] = 'get';
        }
        if (empty($request['parameters'])) {
            $request['parameters'] = array();
        }

        $subRequest = $this->request->create($request['uri'], $request['method'], $request['parameters']);
        if (false === ($parameters = $this->router->match($subRequest->getPathInfo()))) {
            throw new \InvalidArgumentException('uri did not match a route for index: '.$i);
        }

        $subRequest->attributes->add($parameters);
        $subResponse = $this->kernel->handle($subRequest, \Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST);
        if ($subResponse->isRedirect()) {
            $request = array(
                'uri' => $subResponse->headers->get('location'),
                'method' => 'get',
                'parameters' => array(),
            );
            return $this->handleRequest($request, $i);
        }

        return array(
            'id' => $i,
            'status' => $subResponse->getStatusCode(),
            'html' => $subResponse->getContent(),
        );
    }

    /**
     * Handle the index request
     *
     * @param string $_format the format to use in the response (json, html ..)
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($_format)
    {
        $requests = (array)$this->request->get('requests');

        $content = array('response' => array());
        foreach ($requests as $i => $request) {
            if ($_format === 'html') {
                $content['response'][] = $this->handleRequest($request, $i);
            } else {
                try {
                    $content['response'][] = $this->handleRequest($request, $i);
                } catch (\Exception $e) {
                    // TODO: are our error messages safe to be returned?
                    $content['response'][] = array('id' => $i, 'status' => '500', 'html' => $e->getMessage());
                }
            }
        }

        $this->response->setStatusCode(200);
        $content['status'] = 'success';

        // TODO add xml?
        switch ($_format) {
        case 'json':
            $content = json_encode($content);
            break;
        case 'html':
        default:
            $content = '<pre>'.var_export($content, true).'</pre>';
            break;
        }

        $this->response->setContent($content);

        return $this->response;
    }
}
