<?php

namespace DrutinyTests\PolicySource;

use Drutiny\Attribute\AsSource;
use Drutiny\LanguageManager;
use Drutiny\Policy;
use Drutiny\PolicySource\AbstractPolicySource;
use Drutiny\PolicySource\PushablePolicySourceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[AsSource(name: 'test', weight: -10, cacheable: false)]
#[Autoconfigure(tags: ['policy.source'])]
class FakePushablePolicySource extends AbstractPolicySource implements PushablePolicySourceInterface
{
    protected array $policies = [];
    protected function doGetList(LanguageManager $languageManager): array
    {
        return $this->policies;
    }

    public function push(Policy $policy, string $commit_msg = ''):Policy
    {
        return $this->policies[$policy->name] = $policy->with(uri: 'https://example.com/policy/' . $policy->name);
    }
}
