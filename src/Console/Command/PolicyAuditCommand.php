<?php

namespace Drutiny\Console\Command;

use Drutiny\Assessment;
use Drutiny\Profile;
use Drutiny\Report\Format;
use Drutiny\Entity\PolicyOverride;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 *
 */
class PolicyAuditCommand extends DrutinyBaseCommand
{
  use ReportingCommandTrait;
  use LanguageCommandTrait;

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
            'remediate',
            'r',
            InputOption::VALUE_NONE,
            'Allow failed checks to remediate themselves if available.'
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
            FALSE
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
        $progress = $this->getProgressBar();
        $progress->start();
        $this->initLanguage($input);

        $profile = $this->getContainer()->get('profile.factory')->loadProfileByName('empty');

        // Setup any parameters for the check.
        $parameters = [];
        foreach ($input->getOption('set-parameter') as $option) {
            list($key, $value) = explode('=', $option, 2);
            // Using Yaml::parse to ensure datatype is correct.
            $parameters[$key] = Yaml::parse($value);
        }

        $name = $input->getArgument('policy');

        $profile->addPolicies([$name => [
          'name' => $name,
          'parameters' => $parameters
        ]]);

        // Setup the target.
        $target = $this->getTargetFactory()->create($input->getArgument('target'));

        // Get the URLs.
        if ($uri = $input->getOption('uri')) {
          $target->setUri($uri);
        }

        $result = [];

        $profile->setReportingPeriod($this->getReportingPeriodStart($input), $this->getReportingPeriodEnd($input));

        $policies = [];
        $progress->setMessage("Loading policy definitions...");
        foreach ($profile->getAllPolicyDefinitions() as $definition) {
            $policies[] = $definition->getPolicy($this->getPolicyFactory());
        }

        $progress->setMessage("Assessing target...");
        $assessment = $this->getContainer()->get('assessment')
        ->setUri($uri)
        ->assessTarget($target, $policies, $profile->getReportingPeriodStart(), $profile->getReportingPeriodEnd(), $input->getOption('remediate'));

        $progress->finish();
        $progress->clear();

        foreach ($this->getFormats($input, $profile) as $format) {
            $format->setNamespace($this->getReportNamespace($input, $uri));
            $format->render($profile, $assessment);
            foreach ($format->write() as $location) {
              $output->write("Policy Audit written to $location.");
            }
        }
        $output->write("Policy Audit Complete.");

        //
        // $format = $input->getOption('format');
        // $format = $this->getContainer()->get('format.factory')->create($format, $profile->format[$format] ?? []);
        // $format->setOutput(($filepath != 'stdout') ? new StreamOutput(fopen($filepath, 'w')) : $output);
        // $format->render($profile, $assessment)->write();

        // Do not use a non-zero exit code when no severity is set (Default).
        $exit_severity = $input->getOption('exit-on-severity');
        if ($exit_severity === FALSE) {
            return 0;
        }
        $this->logger->info("Exiting with max severity code.");

        // Return the max severity as the exit code.
        $exit_code = $assessment->getSeverityCode();

        return $exit_code >= $exit_severity ? $exit_code : 0;
    }
}
