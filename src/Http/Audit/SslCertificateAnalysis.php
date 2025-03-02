<?php

namespace Drutiny\Http\Audit;

use DateTime;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Audit\AuditInterface;
use Exception;

/**
 *
 */
class SslCertificateAnalysis extends AbstractAnalysis
{
    public function configure(): void
    {
        parent::configure();
        $this->addParameter(
            'domain',
            AuditInterface::PARAMETER_REQUIRED,
            'A domain to gather SSL information from.'
        );
        $this->addParameter(
            'servers',
            AuditInterface::PARAMETER_OPTIONAL,
            'An array of hosts to attempt to retrieve SSL information on a given domain from',
            []
        );
    }

    /**
     * {@inheritdoc}
     */
    public function gather(Sandbox $sandbox)
    {
        $domain = $this->get('domain');

        foreach ($this->buildServersList($this->get('servers'), $domain) as $server) {
            // Ensure we have a hostname and port to connect to.
            list($hostname, $port) = strpos($server, ':') ? explode(':', $server) : [$server, 443];
            $url = 'ssl://'.$hostname . ':' . $port;

            try {
                $certinfo = $this->getSslInformationFromUrl($url, $domain);
                break;
            } catch (Exception $e) {
                $this->logger->warning($e->getMessage());
            }
        }
        $this->set('certificate', $certinfo ?? null);

        if (!isset($certinfo)) {
            return;
        }
        
        $this->set('valid_from', $certinfo['validFrom_time_t']);
        $this->set('valid_till', $certinfo['validTo_time_t']);
    }

    /**
     * Return a list of servers to check for SSL certs from.
     */
    protected function buildServersList(array $servers, string $domain):array
    {
        if (empty($servers)) {
            $dns = dns_get_record($domain, DNS_A);
            $this->set('dns', $dns);
            return array_map(fn($r) => $r['ip'], $dns);
        }
        return $servers;
    }

    /**
     * Get the SSL certificate information from a host for a given domain.
     */
    protected function getSslInformationFromUrl(string $url, string $domain):array
    {
        $context = stream_context_create(["ssl" => [
            "capture_peer_cert" => true,
            "peer_name" => $domain,
            "SNI_enabled" => true
        ]]);

        $this->logger->debug("Getting SSL certificate for $domain from $url.");
        if (!$stream = @stream_socket_client($url, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context)) {
            throw new Exception("$url did not accept an SSL connection: [$errno] $errstr for $domain.");
        }

        $cert = stream_context_get_params($stream);
        fclose($stream);

        return openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
    }
}
