<?php

namespace Drutiny\Console\Command;

use Drutiny\Audit\AuditInterface;
use Drutiny\AuditFactory;
use Drutiny\Policy\AuditClass;
use Drutiny\PolicyFactory;
use Drutiny\Settings;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 *
 */
class AuditListCommand extends DrutinyBaseCommand
{
    public function __construct(
        protected PolicyFactory $policyFactory,
        protected AuditFactory $auditFactory,
        protected LoggerInterface $logger,
        protected Settings $settings
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
        ->setName('audit:list')
        ->setDescription('Show all php audit classes available.')
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get audit classes from the AuditFactory registry keys (includes old renamed class names)
        $audits = array_keys($this->auditFactory->registry);
        sort($audits);
        $policy_list = $this->policyFactory->getPolicyList(true);

        $stats = [];
        foreach ($audits as $audit) {
            $audit = AuditClass::fromClass($audit);
            try {
                $instance = $this->auditFactory->mock($audit->name);
            } catch (ServiceNotFoundException $e) {
                $this->logger->error($e->getMessage());
                continue;
            }

            $deprecated = $instance->isDeprecated() ? ' <fg=yellow>(deprecated)</>' : '';
            $renamed = ($audit->name !== $this->auditFactory->registry[$audit->name]) ? ' <fg=cyan>(renamed)</>' : '';
            $stats[] = [$audit->name.$deprecated.$renamed, $audit->version?->version, count(array_filter($policy_list, function ($policy) use ($audit) {
                return $audit->name == $policy['class'];
            }))];
        }

        $io = new SymfonyStyle($input, $output);
        $io->title('Drutiny Audit Classes');
        $io->table(['Audit', 'Version', 'Policy utilisation'], $stats);
        return 0;
    }
}
