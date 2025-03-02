<?php

namespace Drutiny\Http\Middleware;

use Drutiny\Attribute\PluginField;
use Drutiny\Http\MiddlewareInterface;
use Drutiny\Attribute\Plugin;
use Drutiny\Plugin\FieldType;
use Drutiny\Plugin\PluginCollection;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

#[Plugin(name: 'http:authorization', collectionKey: 'domain', as: '$pluginCollection')]
#[PluginField(
    name: 'domain',
    description: "The domain to apply the HTTP Authorization header to.",
    type: FieldType::CONFIG
)]
#[PluginField(
    name: 'username',
    description: "The username to use for basic http digest authorization",
    type: FieldType::CREDENTIAL
)]
#[PluginField(
    name: 'password',
    description: "The password to use for basic http digest authorization",
    type: FieldType::CREDENTIAL
)]
class Authorization implements MiddlewareInterface
{
    /**
     * @param $container ContainerInterface
     * @param $config @config service.
     */
    public function __construct(protected PluginCollection $pluginCollection, protected LoggerInterface $logger)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function handle(RequestInterface $request)
    {
        $uri = (string) $request->getUri();
        $host = parse_url($uri, PHP_URL_HOST);

        if (!$this->pluginCollection->has($host)) {
            return $request;
        }

        $plugin = $this->pluginCollection->get($host);


        // Do not apply if path isset and doesn't match.
        // $path = $this->config[$host]['path'] ?? false;
        // if ($path && strpos(parse_url($uri, PHP_URL_PATH), $path) === 0) {
        //     return $request;
        // }

        $header_value = 'Basic ' . base64_encode($plugin->username .':'. $plugin->password);

        $this->logger->debug("Using HTTP Authorization for $host: $header_value.");

        return $request->withHeader('Authorization', $header_value);
    }
}
