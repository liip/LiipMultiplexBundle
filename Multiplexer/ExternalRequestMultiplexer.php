<?php

/*
 * This file is part of the Liip/MultiplexBundle
 *
 * (c) Lukas Kahwe Smith <smith@pooteeweet.org>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Liip\MultiplexBundle\Multiplexer;

use Buzz\Browser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * handles external request via buzz
 *
 * @author Robert Schönthal <schoenthal.robert_FR@guj.de>
 */
class ExternalRequestMultiplexer implements MultiplexerInterface
{
     /**
      * @var Browser
      */
     protected $browser;

     /**
      * Constructor
      *
      * @param Browser $browser the Buzz Browser
      */
     public function __construct(Browser $browser)
     {
         $this->browser = $browser;
     }

     /**
      * {inheritDoc}
      */
     public function handleRequest(Request $request, array $requestInfo, MultiplexDispatcher $dispatcher)
     {
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
     * {inheritDoc}
     */
    function supports(array $requestInfo)
    {
        //TODO better check for absolute domains
        return strpos($requestInfo['uri'], '/') != 0;
    }
}
