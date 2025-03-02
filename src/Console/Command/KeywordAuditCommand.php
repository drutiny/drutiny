<?php

namespace Drutiny\Console\Command;

use Drutiny\Assessment;
use Drutiny\LanguageManager;
use Drutiny\PolicyFactory;
use Drutiny\ProfileFactory;
use Drutiny\Report\Format\Terminal;
use Drutiny\Report\FormatFactory;
use Drutiny\Report\ReportFactory;
use Drutiny\Report\StoreFactory;
use Drutiny\Target\TargetFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *
 */
class KeywordAuditCommand extends DrutinyBaseCommand
{
    use ReportingCommandTrait;
    use LanguageCommandTrait;

    public function __construct(
        protected ProfileFactory $profileFactory,
        protected PolicyFactory $policyFactory,
        protected TargetFactory $targetFactory,
        protected FormatFactory $formatFactory,
        protected ReportFactory $reportFactory,
        protected LoggerInterface $logger,
        protected ProgressBar $progressBar,
        protected StoreFactory $storeFactory,
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
        ->setName('keyword:audit')
        ->setDescription('Run a single policy audit against a site.')
        ->addArgument(
            'target',
            InputArgument::REQUIRED,
            'The target to run the check against.'
        )
        ->addOption(
            'keyword',
            'k',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'A keyword to collect policies by.'
        )
        ->addOption(
            'uri',
            'l',
            InputOption::VALUE_OPTIONAL,
            'Provide URLs to run against the target. Useful for multisite installs. Accepts multiple arguments.',
            false
        )
        ->addOption(
            'exit-on-severity',
            'x',
            InputOption::VALUE_OPTIONAL,
            'Send an exit code to the console if a policy of a given severity fails. Defaults to none (exit code 0). (Options: none, low, normal, high, critical)',
            false
        )
        ->addOption(
            'yes',
            'y',
            InputOption::VALUE_NONE,
            'Implicity answer yes to any confirmation prompts.'
        );
        parent::configure();
        $this->configureReporting();
        $this->configureLanguage();
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->progressBar->start();
        $this->initLanguage($input);

        $io = new SymfonyStyle($input, $output);

        // Validate and Setup the target.
        $target = $this->targetFactory->create($input->getArgument('target'), $input->getOption('uri'));

        $list = [];
        foreach ($input->getOption('keyword') as $keyword) {
            $list += $this->policyFactory->getPolicyListByKeyword($keyword);
        }

        $unique_policies = [];
        $rows = [];
        foreach ($list as $policy) {
            $unique_policies[$policy['name']] = [];
            $rows[$policy['name']] = [$policy['title'], $policy['name'], $policy['class'], implode(', ', $policy['tags'] ?? [])];
        }

        if (empty($unique_policies)) {
            $io->warning("No policies found with keywords: " . implode(', ', $input->getOption('keyword')));
            return 0;
        }
        $io->text("Audit these policies:");
        $io->table(['policy', 'name', 'class', 'tags'], $rows);
        if (!$input->getOption('yes') && !$io->confirm("Are you sure you want to audit these policies?")) {
            $io->text('Cancelling');
            return 0;
        } else {
            $io->text('Keyword audit will run these policies.');
            $io->text('');
        }

        $profile = $this->profileFactory->loadProfileByName('empty')->with(
            title: 'Keyword audit',
            name: '_keyword_audit',
            uuid: '_keyword_audit',
            source: 'keyword:audit',
            description: 'Wrapper profile for keyword:audit',
            policies: $unique_policies,
            format: [
            'terminal' => [
              'content' => "
              {% block audit %}
                {% for response in assessment.getResults %}
                    {{ policy_result(response, assessment) }}
                {% endfor %}
              {% endblock %}"
            ]]
        );

        // Get the URLs.
        if ($uri = $input->getOption('uri')) {
            $target->setUri($uri);
        }

        $profile->setReportingPeriod($this->getReportingPeriodStart($input), $this->getReportingPeriodEnd($input));

        $this->progressBar->setMessage("Assessing target...");

        $report = $this->reportFactory->create($profile, $target);

        $this->progressBar->finish();
        $this->progressBar->clear();

        $rows = [];
        foreach ((new Assessment($report))->getStatsByResult() as $type => $frequency) {
            $rows[] = [ucwords($type, " -"), $frequency];
        }

        $io->title('Policy result summary');
        $io->table(
            ['Result', 'Frequency'],
            $rows
        );

        $this->formatReport($report, $io, $input);

        $output->writeln("Keyword Audit Complete.");

        // Do not use a non-zero exit code when no severity is set (Default).
        $exit_severity = $input->getOption('exit-on-severity');
        if ($exit_severity === false) {
            return 0;
        }
        $this->logger->info("Exiting with max severity code.");

        // Return the max severity as the exit code.
        $exit_code = $report->severity->getWeight();

        return $exit_code >= $exit_severity ? $exit_code : 0;
    }
}
