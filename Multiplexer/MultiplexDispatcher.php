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
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * dispatches Requests to single responsible Multiplexer
 *
 * @author Robert Schönthal <schoenthal.robert_FR@guj.de>
 */
class MultiplexDispatcher
{
    private $multiplexers = array();

    private $displayErrors = true;

    /**
     * adds a multiplexer
     *
     * @param MultiplexerInterface $multiplexer
     * @return MultiplexDispatcher
     */
    public function addMultiplexer(MultiplexerInterface $multiplexer)
    {
        $this->multiplexers[] = $multiplexer;

        return $this;
    }

    /**
     * enables or disabled the error messages of each request
     *
     * @param boolean $state
     * @return MultiplexDispatcher
     */
    public function displayErrors($state)
    {
        $this->displayErrors = $state;

        return $this;
    }

    /**
     * multiplexes a bunch of Requests
     *
     * @param Request $request
     * @param string $format
     * @return Response
     */
    public function multiplex(Request $request, $format = null)
    {
        $format = $format ? : $request->getRequestFormat();

        $requests = (array)$request->get('requests', array());
        $responses = array();

        foreach ($requests as $i => $requestInfo) {
            $response = $this->processRequest($request, $requestInfo, $i);
            $responses = array_merge($responses, $response);
        }

        return $this->buildResponse($responses, $format);
    }

    /**
     * processes a request
     *
     * @param Request $request the Symfony Request
     * @param array $requests the requests to process
     * @return array the response
     */
    public function processRequest(Request $request, array $requestInfo)
    {
        $response = array();

        try {
            $requestInfo = $this->sanitizeRequest($request, $requestInfo);
            $multiplexer = $this->findMultiplexerForRequestInfo($requestInfo);
            $response[$requestInfo['uri']] = $multiplexer->handleRequest($request, $requestInfo, $this);
        } catch (\Exception $e) {
            $code = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            $message = $this->displayErrors ? $e->getMessage() : Response::$statusTexts[$code];
            $response[$requestInfo['uri']] = array('request' => $requestInfo['uri'], 'status' => $code, 'response' => $message);
        }

        return $response;
    }

    /**
     * @param array $requestInfo
     * @return MultiplexerInterface
     * @throws HttpException
     */
    private function findMultiplexerForRequestInfo(array $requestInfo)
    {
        foreach ($this->multiplexers as $multiplexer) {
            /* @var MultiplexerInterface $multiplexer */
            if ($multiplexer->supports($requestInfo)) {

                return $multiplexer;
            }
        }

        throw new HttpException('no suitable multiplexer found');
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
            case 'json' :
                return new JsonResponse($responses);
            case 'html' :
                return new Response('<pre>' . var_export($responses, true) . '</pre>');
        }

        throw new HttpException(501, 'Response format ' . $format . ' not yet implemented');
    }

    /**
     * @param Request $request the Symfony Request
     * @param array $requestInfo array contains 'uri', 'method' and 'query'
     * @return array the sanitized request
     * @throws \InvalidArgumentException if the single request is invalid
     */
    private function sanitizeRequest(Request $request, array $requestInfo)
    {
        if (empty($requestInfo['uri'])) {
            throw new \InvalidArgumentException('no uri given');
        }

        $requestInfo['method'] = empty($requestInfo['method']) ? 'GET' : strtoupper($requestInfo['method']);

        if (empty($requestInfo['parameters'])) {
            $requestInfo['parameters'] = array();
        }

        return $requestInfo;
    }
}
