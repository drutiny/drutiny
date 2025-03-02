<?php

namespace Drutiny\Audit\DNS;

use Drutiny\Attribute\DataProvider;
use Drutiny\Attribute\Parameter;
use Drutiny\Audit\AbstractAnalysis;

/**
 * Assert a value is present in the DNS record of the zone.
 */
#[Parameter(name: 'type', mode: Parameter::OPTIONAL, description: 'The type of DNS record to lookup', enums: ['A', 'CNAME', 'MX', 'TXT'], default: 'A')]
#[Parameter(name: 'zone', mode: Parameter::OPTIONAL, description: 'The DNS zone to look up.')]
class DnsAnalysis extends AbstractAnalysis
{
    #[DataProvider]
    public function lookup(): void
    {
        $type = match ($this->getParameter('type')) {
            'A' => DNS_A,
            'CNAME' => DNS_CNAME,
            'MX' => DNS_MX,
            'TXT' => DNS_TXT
        };
        $zone = $this->getParameter('zone', $this->target['domain']);

        // Set the zone incase it wasn't set.
        $this->set('zone', $zone);
        $this->set('dns', dns_get_record($zone, $type));
    }
}
