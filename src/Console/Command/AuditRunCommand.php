<?php

namespace Drutiny\Console\Command;

use Drutiny\LanguageManager;
use Drutiny\Policy;
use Drutiny\ProfileFactory;
use Drutiny\Report\FormatFactory;
use Drutiny\Report\ReportFactory;
use Drutiny\Report\StoreFactory;
use Drutiny\Target\TargetFactory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 *
 */
class AuditRunCommand extends DrutinyBaseCommand
{
    use ReportingCommandTrait;
    use LanguageCommandTrait;

    public function __construct(
        protected TargetFactory $targetFactory,
        protected ReportFactory $reportFactory,
        protected ProfileFactory $profileFactory,
        protected FormatFactory $formatFactory,
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
        ->setName('audit:run')
        ->setDescription('Run a single audit against a site without a policy.')
        ->addArgument(
            'audit',
            InputArgument::REQUIRED,
            'The PHP class (including namespace) of the audit'
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
            'default'
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

        $audit_class = $input->getArgument('audit');

        // Fabricate a policy to run the audit.
        $policy = new Policy(...[
          'title' => 'Audit: ' . $audit_class,
          'name' => '_test',
          'class' => $audit_class,
          'source' => 'audit:run',
          'description' => 'Verbatim run of an audit class',
          'remediation' => 'none',
          'success' => 'success',
          'failure' => 'failure',
          'warning' => 'warning',
          'uuid' => $audit_class,
          'severity' => 'normal'
        ]);

        // Setup any parameters for the check.
        foreach ($input->getOption('set-parameter') as $option) {
            list($key, $value) = explode('=', $option, 2);

            $info = Yaml::parse($value);

            $parameters = $policy->parameters->all();
            $parameters[$key] = $info;
            $policy = $policy->with(parameters: $parameters);
        }

        // Setup the target.
        $target = $this->targetFactory->create($input->getArgument('target'));

        // Setup the reporting report.
        $start = new \DateTime($input->getOption('reporting-period-start'));
        $end   = new \DateTime($input->getOption('reporting-period-end'));

        $uri = $input->getOption('uri');
        
        // If a URI is provided set it on the Target.
        $target->setUri($uri);

        $profile = $this->profileFactory->create([
          'title' => 'Audit:Run',
          'name' => 'audit_run',
          'uuid' => 'audit_run',
          'source' => 'audit:run',
          'description' => 'Wrapper profile for audit:run',
          'policies' => [
            $policy->name => $policy->getDefinition()
          ],
          'format' => [
            'terminal' => [
              'content' => "
              {% block audit %}
                {{ policy_result(assessment.getPolicyResult('{$policy->name}'), assessment) }}
              {% endblock %}"
            ]
          ]
        ]);

        $profile->setReportingPeriod($start, $end);

        $report = $this->reportFactory->create($profile, $target);

        $style = new SymfonyStyle($input, $output);
        if ($report->results[$policy->name]->isIrrelevant()) {
            $style->warning("Policy {$policy->name} was evaluated as irrelevant for the target " . $target->getId());
            if (isset($report->results[$policy->name]->tokens['exception'])) {
                $style->error($report->results[$policy->name]->tokens['exception']);
            }
            return 0;
        } elseif ($report->results[$policy->name]->hasError()) {
            $style->error("Policy $policy->name has an error for the target " . $target->getId());
            $tokens = $report->results[$policy->name]->tokens;
            $style->error($tokens['exception_type'] .': '.$tokens['exception']);
            return 1;
        }

        $this->formatReport($report, $style, $input);

        return $report->successful ? 0 : $report->severity->getWeight();
    }
}
