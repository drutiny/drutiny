<?php

namespace Drutiny\Audit;

use Drutiny\Attribute\DataProvider;
use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit\Exception\AuditException;
use Drutiny\AuditFactory;
use Drutiny\AuditResponse\State;
use Drutiny\Entity\DataBag;
use Drutiny\Sandbox\Sandbox;
use InvalidArgumentException;
use Twig\Error\RuntimeError;

#[Parameter(
    name: 'pipeline',
    description: 'A hash of audit classes to gather data from. The key should be the class name and the value should be a hash of parameters to pass.',
    mode: Parameter::REQUIRED,
    type: Type::HASH,
)]
class AuditAnalysisPipeline extends AbstractAnalysis
{
    /**
     * {@inheritdoc}
     */
    public function prepare(\Drutiny\Policy $policy): ?string
    {
        return 'audit_analysis_pipeline_' . mt_rand(1000, 9999);
    }
    
    #[DataProvider]
    protected function aggregateData(AuditFactory $factory, Sandbox $sandbox):void
    {
        foreach ($this->getParameter('pipeline') as $name => $pipeline) {
            try {
                if (!is_array($pipeline)) {
                    throw new InvalidArgumentException("Pipeline $name must be an array hash.");
                }
                $pipeline['name'] ??= $name;
                $pipeline = new _Pipeline(...$pipeline);
                
                $audit = $factory->mock($pipeline->class, $this->target);
                $audit->setReportingPeriod($this->reportingPeriodStart, $this->reportingPeriodEnd);

                assert($audit instanceof AbstractAnalysis);

                // Transfer token context to audit class.
                $tokens = $this->dataBag->all();
                $tokens['parameters'] = new DataBag;
                $audit->dataBag->add($tokens);
                $audit->prepare($this->policy->with(parameters: $pipeline->parameters));
    
                $this->set($pipeline->name, $audit->getGatheredData($pipeline->parameters, $sandbox)->all());

                if (!$this->evaluate($pipeline->continueIf, 'twig')) {
                    break;
                }
            } catch (RuntimeError $e) {
                $tokens = $audit->dataBag->all();
                throw new AuditException("Failed to evaluate pipeline: $name ({$pipeline->class}).\n" . $e->getMessage() . "\nTokens: " . print_r($tokens, 1), State::ERROR, $e);
            }
        }
    }
}

class _Pipeline
{
    public function __construct(
        public readonly string $name,
        public readonly string $class,
        public readonly array $parameters = [],
        public readonly string $continueIf = 'true'
    ) {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("$class does not exist.");
        }
        if (!is_a($class, AbstractAnalysis::class, true)) {
            throw new InvalidArgumentException("{$class} does not extend AbstractAnalysis. Cannot use this class.");
        }
    }
}
