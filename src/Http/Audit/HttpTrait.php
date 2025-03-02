<?php

namespace Drutiny\Http\Audit;

use Drutiny\Sandbox\Sandbox;
use Drutiny\Http\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;

trait HttpTrait
{
    public function configure():void
    {
        $this->addParameter(
            'use_cache',
            static::PARAMETER_OPTIONAL,
            'Indicator if Guzzle client should use cache middleware.'
        );
        $this->addParameter(
            'options',
            static::PARAMETER_OPTIONAL,
            'An options array passed to the Guzzle client request method.'
        );
        $this->addParameter(
            'force_ssl',
            static::PARAMETER_OPTIONAL,
            'Whether to force SSL',
            true
        );
        $this->addParameter(
            'method',
            static::PARAMETER_OPTIONAL,
            'Which method to use.',
            'GET'
        );
    }

    protected function getHttpResponse(Sandbox $sandbox)
    {

      // This allows policies to specify urls that still contain a domain.
        $url = $this->target->getUri();

        if (strpos($url, 'http') === false) {
            $url = 'https://' . $url;
        }

        if ($this->getParameter('force_ssl', false)) {
            $url = strtr($url, [
            'http://' => 'https://',
            ]);
        }

        $this->set('url', $url);

        $method = $this->getParameter('method', 'GET');

        $this->logger->info(__CLASS__ . ': ' . $method . ' ' . $url);
        $options = $this->getParameter('options', []);

        $handler = HandlerStack::create();

        $client = $this->container->get('http.client')->create([
          'cache' => $this->getParameter('use_cache', true),
          'handler' => $handler,
        ]);

        $handler->unshift(Middleware::mapRequest(function (RequestInterface $request) use ($sandbox) {
            $this->set('req_headers', $request->getHeaders());
            return $request;
        }));

        return $client->request($method, $url, $options);
    }
}
