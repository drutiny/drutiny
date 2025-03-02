<?php

namespace Drutiny\Console\Command;

use Aws\Arn\Exception\InvalidArnException;
use Drutiny\Console\Helper\ProcessManagerViewer;
use Drutiny\Console\ProcessManager;
use Drutiny\Profile;
use Drutiny\DomainSource;
use Drutiny\LanguageManager;
use Drutiny\Policy\Severity;
use Drutiny\PolicyFactory;
use Drutiny\ProfileFactory;
use Drutiny\Report\FormatFactory;
use Drutiny\Report\Report;
use Drutiny\Report\ReportFactory;
use Drutiny\Report\StoreFactory;
use Drutiny\Target\Exception\InvalidTargetException;
use Drutiny\Target\Exception\TargetLoadingException;
use Drutiny\Target\Exception\TargetNotFoundException;
use Drutiny\Target\TargetFactory;
use Drutiny\Target\TargetInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Process\Process;

/**
 * Run a profile and generate a report.
 */
class ProfileRunCommand extends DrutinyBaseCommand
{
    use ReportingCommandTrait;
    use DomainSourceCommandTrait;
    use LanguageCommandTrait;

    public const EXIT_INVALID_TARGET = 114;

    public function __construct(
        protected PolicyFactory $policyFactory,
        protected ProfileFactory $profileFactory,
        protected ReportFactory $reportFactory,
        protected DomainSource $domainSource,
        protected TargetFactory $targetFactory,
        protected LoggerInterface $logger,
        protected ProgressBar $progressBar,
        protected FormatFactory $formatFactory,
        protected EventDispatcher $eventDispatcher,
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
        parent::configure();

        $this
        ->setName('profile:run')
        ->setDescription('Run a profile of checks against a target.')
        ->addArgument(
            'profile',
            InputArgument::REQUIRED,
            'The name of the profile to run.'
        )
        ->addArgument(
            'target',
            InputArgument::REQUIRED,
            'The target to run the policy collection against.'
        )
        ->addOption(
            'uri',
            'l',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Provide URLs to run against the target. Useful for multisite installs. Accepts multiple arguments.',
            []
        )
        ->addOption(
            'exit-on-severity',
            'x',
            InputOption::VALUE_OPTIONAL,
            'Send an exit code to the console if a policy of a given severity fails. Defaults to none (exit code 0). (Options: none, low, normal, high, critical)',
            false
        )
        ->addOption(
            'exclude-policy',
            'e',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Specify policy names to exclude from the profile that are normally listed.',
            []
        )
        ->addOption(
            'include-policy',
            'p',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Specify policy names to include in the profile in addition to those listed in the profile.',
            []
        )
        ->addOption(
            'report-summary',
            null,
            InputOption::VALUE_NONE,
            'Flag to additionally render a summary report for all targets audited.'
        )
        ->addOption(
            'title',
            't',
            InputOption::VALUE_OPTIONAL,
            'Override the title of the profile with the specified value.',
            false
        )
        ->addOption(
            'pipe',
            null,
            InputOption::VALUE_NONE,
            'Pipe the output instead of formatting it.',
        )
        ;
        $this->configureReporting();
        $this->configureDomainSource($this->domainSource);
        $this->configureLanguage();
    }

    /**
     * Prepare profile from input options.
     */
    protected function prepareProfile(InputInterface $input): Profile
    {
        $this->progressBar->setMessage("Loading profile..");
        $this->progressBar->display();
        $profile = $this->profileFactory->loadProfileByName($input->getArgument('profile'));

        // Override the title of the profile with the specified value.
        if ($title = $input->getOption('title')) {
            $profile = $profile->with(title: $title);
        }

        // Allow command line to add policies to the profile.
        $included_policies = $input->getOption('include-policy');
        if (!empty($included_policies)) {
            $extra = array_combine($included_policies, array_pad([], count($included_policies), []));
            $profile = $profile->with(policies: $extra);
        }

        // Allow command line omission of policies highlighted in the profile.
        // WARNING: This may remove policy dependants which may make polices behave
        // in strange ways.
        $excluded_policies = $input->getOption('exclude-policy') ?? [];
        if (!empty($excluded_policies)) {
            $profile = $profile->with(excluded_policies: $excluded_policies);
        }

        $profile->setReportingPeriod($this->getReportingPeriodStart($input), $this->getReportingPeriodEnd($input));
        $this->progressBar->advance();

        return $profile;
    }

    /**
     * Load URIs from input options.
     */
    protected function loadUris(InputInterface $input): array
    {
        // Get the URLs.
        $this->progressBar->setMessage("Loading URIs..");
        $this->progressBar->display();

        $uris = $input->getOption('uri');

        $domains = [];
        foreach ($this->parseDomainSourceOptions($input) as $source => $options) {
            $this->logger->debug("Loading domains from $source.");
            $target ??= $this->targetFactory->create($input->getArgument('target'));
            $domains = array_merge($this->domainSource->getDomains($target, $source, $options), $domains);
        }

        if (!empty($domains)) {
            // Merge domains in with the $uris argument.
            // Omit the "default" key that is present by default.
            $uris = array_merge($domains, ($uris === ['default']) ? [] : $uris);
        }
        $this->progressBar->advance();
        return empty($uris) ? [null] : $uris;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (count($input->getOption('uri')) > 1 && in_array('terminal', $input->getOption('format'))) {
            throw new InvalidArgumentException("format option is required when passing multiple URIs.");
        }

        $this->initLanguage($input);

        $this->progressBar->start(2);

        $profile = $this->prepareProfile($input, $this->progressBar);
        $uris = $this->loadUris($input);

        $console = new SymfonyStyle($input, $output);

        $exit_codes = [Command::SUCCESS];

        // Run multisite audits in seperate processes.
        if (count($uris) > 1) {
            $this->progressBar->clear();
            $exit_codes = $this->asyncExecuteWithUpdates($input, $output, $uris);
        } else {
            $uri = array_shift($uris);
            $report_uris = [];
            try {
                $target = $this->targetFactory->create($input->getArgument('target'), $uri);
                
                $report = $this->reportFactory->promise(
                    profile: $profile,
                    target: $target
                );

                $this->progressBar->clear();

                if ($report instanceof ProcessManager) {
                    if ($output instanceof ConsoleOutput && !$input->getOption('no-interaction')) {
                        $report = $this->waitForReportWithUpdates($report, $output, $target);
                    } else {
                        $report = $this->waitForReport($report);
                    }
                }
    
                $report_uris = $this->formatReport($report, $console, $input);
                $exit_codes[] = $report->successful ? 0 : $report->severity->getWeight();
            } catch (TargetLoadingException | TargetNotFoundException | InvalidTargetException $e) {
                $console->error($e->getMessage());
                $exit_codes[] = $e::ERROR_CODE;
            } catch (\Exception $e) {
                $this->logger->error("{$profile->name} audit on $uri failed: " . $e->getMessage());
                $console->error("{$profile->name} audit on $uri failed: " . $e->getMessage());
                $exit_codes[] = 17;
                throw $e;
            } catch (\Error $e) {
                $this->logger->error("{$profile->name} audit on $uri failed: " . $e->getMessage());
                $console->error("{$profile->name} audit on $uri failed: " . $e->getMessage());
                $exit_codes[] = 17;
                throw $e;
            }
            if ($input->getOption('pipe')) {
                $output->write(base64_encode(json_encode([
                    'severity' => max($exit_codes),
                    'uri' => $uri,
                    'report_uris' =>  $report_uris
                ])));
                return Command::SUCCESS;
            }

            foreach ($report_uris as $uri) {
                $console->success("Written $uri");
            }
            $output->writeln("");
        }

        // Do not use a non-zero exit code when no severity is set (Default).
        $exit_severity = $input->getOption('exit-on-severity');
        if ($exit_severity === false) {
            return Command::SUCCESS;
        }
        $exit_code = max($exit_codes);

        return $exit_code >= $exit_severity ? $exit_code : Command::SUCCESS;
    }

    protected function asyncExecuteWithUpdates(InputInterface $input, ConsoleOutput $output, array $uris):array
    {
        $processManager = new ProcessManager($this->logger);
        $processManager->maxConcurrency = 3;
        foreach ($uris as $uri) {
            // Grab all options excluding domain-source options.
            $options = array_filter($input->getOptions(), function ($key) {
                return strpos($key, 'domain-source') === false;
            }, \ARRAY_FILTER_USE_KEY);

            // We're only sending a single URL.
            $options['uri'] = [$uri];

            // We don't want the policy level updates, just the overall progress.
            $options['no-interaction'] = true;

            if (empty($options['store'])) {
                unset($options['store']);
            }

            $cmd = ['profile:run', $input->getArgument('profile'), $input->getArgument('target')];

            foreach ($options as $key => $value) {
                $definition = $this->getDefinition()->getOption($key);
                if (!$definition->acceptValue() && $value) {
                    $cmd[] = '--' . $key;
                    continue;
                }
                $values = is_array($value) ? $value : [$value];

                foreach ($values as $value) {
                    if ($definition->acceptValue()) {
                        $cmd[] = '--' . $key . '=' . $value;
                    }
                }
            }
            $cmd[] = '--pipe';
            $processManager->add(ProcessManager::create($cmd), name: $uri);
        }
        $processManager->then(function (array $processes) {
            return array_map(function (Process $process) {
                return json_decode(base64_decode($process->getOutput()), true);
            }, $processes);
        });

        $viewer = new ProcessManagerViewer($output, $processManager);
        $viewer->setHeaders(['URI', 'Status', 'Timing'])
            ->onStatusChange(function ($status, $name, Process $process) {
                $report_uri = null;
                if ($status == ProcessManagerViewer::STATUS_TERMINATED && $process->isSuccessful()) {
                    $output = json_decode(base64_decode($process->getOutput()), true);
                    $report_uri = $output['report_uris'][0] ?? null;
                }
                return match ($status) {
                    ProcessManagerViewer::STATUS_PENDING => '<fg=yellow>Waiting to start</>',
                    ProcessManagerViewer::STATUS_RUNNING => 'In progress',
                    ProcessManagerViewer::STATUS_TERMINATED => '<fg=green>' . ($report_uri ?? 'Complete') . '</>',
                };
            })
            ->onUpdate(function (Process $process, string $name) {
                $stderr = $process->getIncrementalErrorOutput();
                $process->clearErrorOutput();
                if (empty($stderr)) {
                    return;
                }
                $lines = array_filter(array_map('trim', explode(PHP_EOL, $stderr)));
                if (count($lines)) {
                    return array_pop($lines);
                }
            })
            ->render()
            ->watchAndWait();
        $viewer->clean();

        $results = $processManager->resolve();

        $io = new SymfonyStyle($input, $output);
        $io->table(
            headers: ['URI', 'Severity', 'Reports'],
            rows: array_map(function ($result) {
                try {
                    $severity = Severity::fromInt($result['severity'])->name;
                } catch (\Exception $e) {
                    $severity = 'NONE';
                }
                return [
                    $result['uri'],
                    $severity,
                    implode(PHP_EOL, $result['report_uris'])
                ];
            }, $results)
        );

        return array_map(fn($r) => $r['severity'], $results);
    }

    /**
     * Wait for a streaming report to resolve.
     */
    protected function waitForReportWithUpdates(ProcessManager $report, ConsoleOutput $output, TargetInterface $target): Report
    {
        $viewer = new ProcessManagerViewer($output, $report);
        $viewer->setHeaders([$target['domain'], 'Status', 'Timing'])
            ->onStatusChange(function ($status) {
                return match ($status) {
                    ProcessManagerViewer::STATUS_PENDING => '<fg=yellow>Waiting to start</>',
                    ProcessManagerViewer::STATUS_RUNNING => 'In progress',
                    ProcessManagerViewer::STATUS_TERMINATED => '<fg=green>Complete</>',
                };
            })
            ->render()
            ->watchAndWait();
        $viewer->clean();

        /**
         * @var \Drutiny\Report
         */
        return $report->resolve();
    }

    protected function waitForReport(ProcessManager $report): Report
    {
        while (!$report->hasFinished()) {
            $total = $report->length();
            $active = count($report->getActive());
            $complete = count($report->getCompleted());
            $this->progressBar->setMaxSteps($total);
            $this->progressBar->setProgress($complete);
            $this->progressBar->setMessage(date('H:i:s') . " $active in progress. $complete/$total complete.");
            $this->progressBar->display();
            sleep(1);
            $report->update();
        }
        return $report->resolve();
    }
}
