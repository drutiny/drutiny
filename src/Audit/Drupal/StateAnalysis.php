<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Attribute\DataProvider;
use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Policy;
use Drutiny\Policy\Dependency;
use Drutiny\Target\Service\Drush;

#[Parameter(
    name: 'state',
    description: 'The name of the state to fetch.',
    type: Type::STRING,
    mode: Parameter::REQUIRED
)]
#[Dependency(expression: 'Drupal.isVersion8orLater')]
class StateAnalysis extends AbstractAnalysis
{
    protected array $states = [];

    /**
     * {@inheritdoc}
     */
    public function prepare(Policy $policy): ?string
    {
        $this->states[] = $policy->parameters->get('state');
        return __CLASS__;
    }

    #[DataProvider]
    protected function getState():void
    {
        $drush = $this->target->getService('drush');
        assert($drush instanceof Drush);

        $key = $this->getParameter('state');

        $keys = array_unique($this->states);

        $states = $drush->runtime(function (array $states) {
            return \Drupal::state()->getMultiple($states);
        },
        $keys);

        $this->set('value', $states[$key] ?? null);
    }
}
