<?php

namespace Drutiny\Http\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 *
 * @Token(
 *  name = "header_value",
 *  description = "The value to check against.",
 *  type = "string"
 * )
 * @Token(
 *  name = "request_error",
 *  description = "If the request failed, this token will contain the error message.",
 *  type = "string"
 * )
 */
class HttpHeaderExists extends Http
{

    public function configure():void
    {
         $this->addParameter(
             'header',
             static::PARAMETER_OPTIONAL,
             'The HTTP header to check the value of.'
         );
        $this->HttpTrait_configure();
    }


  /**
   *
   */
    public function audit(Sandbox $sandbox)
    {
        try {
            $this->set('header', $this->getParameter('header'));
            $res = $this->getHttpResponse($sandbox);
            if ($has_header = $res->hasHeader($this->getParameter('header'))) {
                $headers = $res->getHeader($this->getParameter('header'));
                $this->set('header_value', $headers[0]);
            }
            return $has_header;
        } catch (RequestException $e) {
            $sandbox->logger()->error($e->getMessage());
            $this->set('request_error', $e->getMessage());
            throw new \Exception("The audit was not able to get the HTTP headers; HTTP result code=" . $e->getCode());
            return self::ERROR;
        }
    }
}
