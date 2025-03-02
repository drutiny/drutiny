<?php

namespace Drutiny\Audit\DNS;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Sandbox\Sandbox;
use InvalidArgumentException;

/**
 * Provide information about the authority of a DNS entry.
 */
class DnsAuthority extends AbstractAnalysis
{

    public function configure():void
    {
        parent::configure();
        $this->addParameter(
            'zone',
            static::PARAMETER_OPTIONAL,
            '',
        );
    }

    public function gather(Sandbox $sandbox)
    {
        $zone = $this->getParameter('zone', $this->target['domain']);
        $dns = dns_get_record($zone, DNS_A);
        $ns_records = [];
        foreach ($dns as $record) {
            $host_parts = explode('.', $record['host']);
            do {
                $host = implode('.', $host_parts);
                $host_ns_records = dns_get_record($host, DNS_NS);
                array_shift($host_parts);
            } while (empty($host_ns_records) && !empty($host_parts));
            $ns_records = array_merge($ns_records, $host_ns_records);
        }

        // Set the zone incase it wasn't set.
        $this->set('zone', $zone);
        $this->set('ns_host', $host);
        $this->set('dns', $dns);
        $this->set('ns_records', $ns_records);
    }
}
