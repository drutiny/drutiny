<?php

namespace Drutiny\Http\Audit;

use Drutiny\Sandbox\Sandbox;
use GuzzleHttp\Exception\RequestException;

/**
 *
 */
class HttpHeaderMatch extends Http
{
    public function configure():void
    {
        $this->addParameter(
            'header',
            static::PARAMETER_REQUIRED,
            'The HTTP header to check the value of.'
        );
        $this->addParameter(
            'header_value',
            static::PARAMETER_REQUIRED,
            'The value to check against.'
        );
        $this->HttpTrait_configure();
    }

    public function audit(Sandbox $sandbox)
    {
        try {
            $value = $this->getParameter('header_value');
            $res = $this->getHttpResponse($sandbox);
            $header = $this->getParameter('header');

            if (!$res->hasHeader($header)) {
                return false;
            }
            $headers = $res->getHeader($header);
            return $value == $headers[0];
        }
        catch (RequestException $e) {
            $sandbox->logger()->error($e->getMessage());
            $this->set('request_error', $e->getMessage());
            throw new \Exception("The audit was not able to get the HTTP headers; HTTP result code=" . $e->getCode());
            return self::ERROR;
        }
    }
}
