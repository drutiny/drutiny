<?php

namespace Drutiny\Attribute;

use Attribute;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Attribute(Attribute::TARGET_CLASS)]
class CompilerPass
{
    public function __construct(
        public readonly string $type = PassConfig::TYPE_BEFORE_OPTIMIZATION,
        public readonly int $priority = 0
    ) {
    }

    public function configure(ContainerBuilder $container, CompilerPassInterface $pass): void
    {
        $container->addCompilerPass($pass, $this->type, $this->priority);
    }
}
