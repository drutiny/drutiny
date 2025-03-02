<?php

namespace Drutiny\Console\Command;

use Drutiny\LanguageManager;
use Drutiny\PolicyFactory;
use Drutiny\ProfileFactory;
use Drutiny\Report\FormatFactory;
use Drutiny\Report\ReportFactory;
use Drutiny\Report\StoreFactory;
use Drutiny\Target\TargetFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 *
 */
class PolicyAuditBatchCommand extends DrutinyBaseCommand
{
    use ReportingCommandTrait;
    use LanguageCommandTrait;

    public function __construct(
        protected ProfileFactory $profileFactory,
        protected PolicyFactory $policyFactory,
        protected TargetFactory $targetFactory,
        protected ReportFactory $reportFactory,
        protected FormatFactory $formatFactory,
        protected StoreFactory $storeFactory,
        protected LoggerInterface $logger,
        protected LanguageManager $languageManager
    ) {
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('policy:audit:batch')
        ->setDescription('Run a batch of policies audit against a site.')
        ->setHidden()
        ->addArgument(
            'target',
            InputArgument::REQUIRED,
            'The target to run the check against.'
        )
        ->addOption('policy-definition', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'The policy definition to run as a base64 encoded object.');
        parent::configure();
        $this->configureReporting();
        $this->configureLanguage();
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // We don't want PHP messages to log to stdout.
        ini_set('display_errors', 'stderr');
        $this->initLanguage($input);

        $polices = [];
        foreach ($input->getOption('policy-definition') as $encoded) {
          /**
           * @var \Drutiny\Profile\PolicyDefinition
           */
            $policy_definition = unserialize(base64_decode($encoded));
            $polices[$policy_definition->name] = $policy_definition;
        }

        $this->logger->info("Loading profile...");

        $profile = $this->profileFactory->create([
          'title' => 'Policy batch audit',
          'name' => '_policy_audit_batch',
          'uuid' => '_policy_audit_batch',
          'source' => 'policy:audit:batch',
          'description' => 'Wrapper profile for policy:audit:batch',
          'policies' => $polices,
        ]);

        $this->logger->info("Loading target...");

        // Setup the target.
        $target = $this->targetFactory->create($input->getArgument('target'));

        $profile->setReportingPeriod($this->getReportingPeriodStart($input), $this->getReportingPeriodEnd($input));

        $this->logger->info("Assessing target...");

        $report = $this->reportFactory->create($profile, $target);

        $output->write(base64_encode(serialize($report->results)));
        return Command::SUCCESS;
    }
}
