<?php

namespace Drutiny\Audit;

use Drutiny\Attribute\DataProvider;
use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\AuditFactory;
use Drutiny\PolicyFactory;

#[Parameter('policy', 'The name of the policy to evaluate', Parameter::REQUIRED, Type::STRING)]
class AuditResponseAnalysis extends AbstractAnalysis
{

    #[DataProvider]
    protected function gather(PolicyFactory $policyFactory, AuditFactory $auditFactory)
    {
        $name = $this->getParameter('policy');
        $policy = $policyFactory->loadPolicyByName($name);
        $audit = $auditFactory->get($policy, $this->target);
        $audit->setReportingPeriod($this->reportingPeriodStart, $this->reportingPeriodEnd);
        $response = $audit->execute($policy);
        $this->set('response', $response);
    }
}
