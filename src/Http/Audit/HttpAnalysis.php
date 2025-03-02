<?php

namespace Drutiny\Http\Audit;

use Drutiny\Attribute\DataProvider;
use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Audit\DynamicParameterType;
use Drutiny\Http\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 *
 */
#[Parameter(name: 'url', description: 'The url to request', type: Type::STRING, default: '{uri}', preprocess: DynamicParameterType::REPLACE)]
#[Parameter(name: 'send_warming_request', description: 'Send a warming request and store headers into cold_headers parameter.', type: Type::BOOLEAN, default: false)]
#[Parameter(name: 'use_cache', description: 'Indicator if Guzzle client should use cache middleware.', type: Type::BOOLEAN, default: false)]
#[Parameter(name: 'options', description: 'An options array passed to the Guzzle client request method.', type: Type::HASH, default: [])]
#[Parameter(name: 'force_ssl', description: 'Whether to force SSL.', type: Type::BOOLEAN, default: true)]
#[Parameter(name: 'method', description: 'Which method to use.', type: Type::STRING, default: 'GET')]
class HttpAnalysis extends AbstractAnalysis
{
    #[DataProvider]
    protected function makeHttpRequest(Client $client): void
    {
        $use_cache = $this->getParameter('use_cache', false);
        // For checking caching functionality, add a listener
        // to pre-warm the origin.
        if ($this->getParameter('send_warming_request', false)) {
            $this->setParameter('use_cache', false);
            $response = $this->getHttpResponse($client);
            $this->set('cold_headers', $this->gatherHeaders($response));
        }

        $this->setParameter('use_cache', $use_cache);
        $response = $this->getHttpResponse($client);

        // Maintain for backwards compatibility.
        $this->set('headers', $this->gatherHeaders($response));
        $this->set('res_headers', $response->getHeaders());
        $this->set('status_code', $response->getStatusCode());
    }

    /**
     * Retrieve a URL to request.
     */
    protected function prepareUrl():string
    {
        // This allows policies to specify urls that still contain a domain.
        $url = $this->getParameter('url');

        if (strpos($url, 'http') === false) {
            $url = 'https://' . $url;
        }

        if ($this->getParameter('force_ssl', false)) {
            $url = strtr($url, [
                'http://' => 'https://',
            ]);
        }

        $this->set('url', $url);
        return $url;
    }

    protected function getHttpResponse(Client $client): ResponseInterface
    {
        // This allows policies to specify urls that still contain a domain.
        $url = $this->prepareUrl();
        $method = $this->getParameter('method');

        $this->logger->info(__CLASS__ . ': ' . $method . ' ' . $url);
        $options = $this->getParameter('options');

        $handler = HandlerStack::create();

        $client = $client->create([
          'cache' => $this->getParameter('use_cache'),
          'handler' => $handler,
        ]);

        $handler->unshift(Middleware::mapRequest(function (RequestInterface $request) {
            $this->set('req_headers', $request->getHeaders());
            return $request;
        }));

        return $client->request($method, $url, $options);
    }

    /**
     * Gather the headers from a response.
     */
    protected function gatherHeaders(ResponseInterface $response): array
    {
        $headers = [];

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $directives = array_map('trim', explode(',', $value));
                foreach ($directives as $directive) {
                    list($flag, $flag_value) = strpos($directive, '=') ? explode('=', $directive) : [$directive, null];

                    $headers[strtolower($name)][strtolower($flag)] = is_null($flag_value) ?: $flag_value;
                }
            }
        }

        foreach ($headers as $name => $values) {
            if (count($values) == 1 && current($values) === true) {
                $headers[$name] = key($values);
            }
        }

        return $headers;
    }
}
