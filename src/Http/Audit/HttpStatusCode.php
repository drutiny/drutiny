<?php

namespace Drutiny\Http\Audit;

use Drutiny\Sandbox\Sandbox;

class HttpStatusCode extends Http
{
  public function configure()
  {
      $this->addParameter(
          'status_code',
          static::PARAMETER_OPTIONAL,
          'The desired value of the HTTP status code.'
      );
      $this->HttpTrait_configure();
  }

  public function audit(Sandbox $sandbox)
  {
      $status_code = $sandbox->getParameter('status_code', 200);
      $res = $this->getHttpResponse($sandbox);
      return $status_code == $res->getStatusCode();
  }
}
