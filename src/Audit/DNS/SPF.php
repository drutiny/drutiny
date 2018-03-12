<?php

namespace Drutiny\Audit\DNS;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

class SPF extends Audit {
  public function audit(Sandbox $sandbox)
  {
    $type = $sandbox->getParameter('type', 'A');
    $uri = $sandbox->getTarget()->uri();
    $domain = preg_match('/^http/', $uri) ? parse_url($uri, PHP_URL_HOST) : $uri;
    $zone = $sandbox->getParameter('zone', $domain);

    // Set the zone incase it wasn't set.
    $sandbox->setParameter('zone', $zone);

    $values = $sandbox->localExec(strtr('dig +short @type @zone', [
      '@type' => $type,
      '@zone' => $zone,
    ]));

    $values = array_map('trim', explode(PHP_EOL, $values));
    $values = array_filter($values);

    $matching_value = $sandbox->getParameter('matching_value');
    return (bool) count(array_filter($values, function ($txt) use ($matching_value) {
      return strpos($txt, $matching_value) !== FALSE;
    }));
  }
}
 ?>
