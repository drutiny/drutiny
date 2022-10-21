<?php

namespace Drutiny\Http\Audit;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Sandbox\Sandbox;
use Psr\Http\Message\ResponseInterface;

/**
 *
 */
class HttpAnalysis extends AbstractAnalysis
{
    use HttpTrait {
        configure as HttpTrait_configure;
    }

    public function configure()
    {
        parent::configure();
        $this->addParameter(
            'send_warming_request',
            static::PARAMETER_OPTIONAL,
            'Send a warming request and store headers into cold_headers parameter.'
        );

        $this->HttpTrait_configure();
    }

    protected function gather(Sandbox $sandbox)
    {
        $use_cache = $this->getParameter('use_cache', false);
      // For checking caching functionality, add a listener
      // to pre-warm the origin.
        if ($this->set('send_warming_request', false)) {
            $this->set('use_cache', false);
            $response = $this->getHttpResponse($sandbox);
            $this->set('cold_headers', $this->gatherHeaders($response));
        }

        $this->set('use_cache', $use_cache);
        $response = $this->getHttpResponse($sandbox);

      // Maintain for backwards compatibility.
        $this->set('headers', $this->gatherHeaders($response));
        $this->set('res_headers', $response->getHeaders());
        $this->set('status_code', $response->getStatusCode());
    }

    protected function gatherHeaders(ResponseInterface $response)
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
