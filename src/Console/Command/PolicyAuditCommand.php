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
class PolicyAuditCommand extends DrutinyBaseCommand
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
        ->setName('policy:audit')
        ->setDescription('Run a single policy audit against a site.')
        ->addArgument(
            'policy',
            InputArgument::REQUIRED,
            'The name of the check to run.'
        )
        ->addArgument(
            'target',
            InputArgument::REQUIRED,
            'The target to run the check against.'
        )
        ->addOption(
            'set-parameter',
            'p',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Set parameters for the check.',
            []
        )
        ->addOption(
            'uri',
            'l',
            InputOption::VALUE_OPTIONAL,
            'Provide URLs to run against the target. Useful for multisite installs. Accepts multiple arguments.',
            false
        )
        ->addOption('pipe', null, InputOption::VALUE_NONE, 'Output in pipe-mode for internal Drutiny use.')
        ->addOption(
            'exit-on-severity',
            'x',
            InputOption::VALUE_OPTIONAL,
            'Send an exit code to the console if a policy of a given severity fails. Defaults to none (exit code 0). (Options: none, low, normal, high, critical)',
            false
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
        $this->initLanguage($input);

        $name = $input->getArgument('policy');

        // Setup any parameters for the check.
        $parameters = [];
        foreach ($input->getOption('set-parameter') as $option) {
            list($key, $value) = explode('=', $option, 2);
            // Using Yaml::parse to ensure datatype is correct.
            $parameters[$key] = Yaml::parse($value);
        }

        $policy_definition = [$name => [
          'name' => $name,
          'parameters' => $parameters
        ]];

        $this->logger->info("Loading profile...");

        $profile = $this->profileFactory->create([
          'title' => 'Policy audit: ' . $name,
          'name' => '_policy_audit',
          'uuid' => '_policy_audit',
          'source' => 'policy:audit',
          'description' => 'Wrapper profile for policy:audit',
          'policies' => $policy_definition,
          'format' => [
            'terminal' => [
              'content' => "
              {% block audit %}
                {{ policy_result(assessment.getPolicyResult('$name'), assessment) }}
              {% endblock %}"
            ]
          ]
        ]);

        $this->logger->info("Loading target...");

        // Setup the target.
        $target = $this->targetFactory->create($input->getArgument('target'), $input->getOption('uri'));

        // Get the URLs.
        if ($uri = $input->getOption('uri')) {
            $target->setUri($uri);
        }

        $profile->setReportingPeriod($this->getReportingPeriodStart($input), $this->getReportingPeriodEnd($input));

        $this->logger->info("Assessing target...");

        $report = $this->reportFactory->create($profile, $target);

        $response = $report->results[$name];

        if ($input->getOption('pipe')) {
            $output->write(base64_encode(serialize($response)));
            return Command::SUCCESS;
        }

        $style = new SymfonyStyle($input, $output);
        if ($response->state->isIrrelevant()) {
            $style->warning("Policy $name was evaluated as irrelevant for the target " . $target->getId());
            if (isset($response->tokens['exception'])) {
                $style->error($response->tokens['exception']);
            }
            return 0;
        } elseif ($response->state->hasError()) {
            $style->error("Policy $name has an error for the target " . $target->getId());
            $tokens = $response->tokens;
            $style->error($tokens['exception_type'] .': '.$tokens['exception'] . ' in ' . ($tokens['file'] ?? 'unknown file') . ' on line ' . ($tokens['line'] ?? 'unknown'));
            $style->error(implode(PHP_EOL, $tokens['trace'] ?? ['Stacktrace not provided.']));
            return 1;
        }

        $this->formatReport($report, $style, $input);

        $output->writeln("Policy Audit Complete.");

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
