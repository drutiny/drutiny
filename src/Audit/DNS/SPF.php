<?php

namespace Drutiny\Audit\DNS;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

/**
 * Assert a value is present in the DNS record of the zone.
 *
 * @Param(
 *  name = "zone",
 *  description = "A list of fields returned from the query to be available globally (outside of a row).",
 *  type = "array"
 * )
 */
class SPF extends Audit
{

    public function configure():void
    {
        $this->addParameter(
            'type',
            static::PARAMETER_OPTIONAL,
            'The type of DNS record to lookup',
            'A'
        );
        $this->addParameter(
            'zone',
            static::PARAMETER_OPTIONAL,
            '',
        );
        $this->addParameter(
            'matching_value',
            static::PARAMETER_REQUIRED,
            'A value that should be present in the queried DNS record.',
        );
    }

    public function audit(Sandbox $sandbox)
    {
        $type = $this->getParameter('type', 'A');
        $uri = $this->target['uri'];
        $domain = preg_match('/^http/', $uri) ? parse_url($uri, PHP_URL_HOST) : $uri;
        $zone = $this->getParameter('zone', $domain);

        // Set the zone incase it wasn't set.
        $this->set('zone', $zone);

        $cmd = strtr('dig +short @type @zone', [
            '@type' => $type,
            '@zone' => $zone
        ]);

        $values = $this->target->getService('local')->run($cmd, function ($output) {
            $output = array_map('trim', explode(PHP_EOL, $output));
            return array_filter($output);
        });

        $matching_value = $this->getParameter('matching_value') ?? $this->get('matching_value');
        return (bool) count(
            array_filter(
                $values,
                function ($txt) use ($matching_value) {
                    return strpos($txt, $matching_value) !== false;
                }
            )
        );
    }
}
