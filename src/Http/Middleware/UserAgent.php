<?php

namespace Drutiny\Http\Middleware;

use Drutiny\Attribute\Plugin;
use Drutiny\Attribute\PluginField;
use Drutiny\Http\MiddlewareInterface;
use Drutiny\Plugin as DrutinyPlugin;
use Psr\Http\Message\RequestInterface;

#[Plugin(name: 'http:user_agent')]
#[PluginField(
    name:'user_agent',
    description: "The User-Agent string for Drutiny to use on outbound HTTP requests."
)]
class UserAgent implements MiddlewareInterface
{
  /**
   * @param $config @config service.
   */
    public function __construct(protected DrutinyPlugin $plugin)
    {
    }

  /**
   * {@inheritdoc}
   */
    public function handle(RequestInterface $request)
    {
        if (!$this->plugin->isInstalled()) {
            return $request;
        }
        return $request->withHeader('User-Agent', $this->plugin->user_agent);
    }
}
