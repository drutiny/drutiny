<?php

namespace Drutiny\Audit;

use Drutiny\Attribute\DataProvider;
use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Psr\Container\ContainerInterface;

#[Parameter(
    name: 'services',
    description: 'An array list of container services to load into the policy runtime.',
    default: [],
    type: Type::HASH
)]
class RuntimeAnalysis extends AbstractAnalysis
{
    
    #[DataProvider]
    protected function getRuntime(ContainerInterface $container): void
    {
        foreach ($this->getParameter('services') as $key => $value) {
            $this->set($key, $container->get($value));
        }
    }
}
