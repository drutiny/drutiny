<?php

namespace Drutiny\Report;

use DateTime;
use DateTimeInterface;
use Drutiny\Audit\AuditInterface;
use Drutiny\Audit\Exception\AuditException;
use Drutiny\Audit\SyntaxProcessor;
use Drutiny\AuditFactory;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\AuditResponse\State;
use Drutiny\Console\ProcessManager;
use Drutiny\LanguageManager;
use Drutiny\Policy;
use Drutiny\Policy\Dependency;
use Drutiny\Policy\DependencyBehaviour;
use Drutiny\Policy\DependencyException;
use Drutiny\PolicyFactory;
use Drutiny\Profile;
use Drutiny\Profile\PolicyDefinition;
use Drutiny\Settings;
use Drutiny\Target\TargetExport;
use Drutiny\Target\TargetInterface;
use Error;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Process\Process;
use Twig\Error\RuntimeError;
use UnexpectedValueException;

class ReportFactory
{
    public function __construct(
        protected LoggerInterface $logger,
        protected AuditFactory $auditFactory,
        protected PolicyFactory $policyFactory,
        protected SyntaxProcessor $syntaxProcessor,
        protected Settings $settings,
        protected EventDispatcher $eventDispatcher,
        protected LanguageManager $languageManager,
        protected ProgressBar $progressBar,
    ) {
    }

    /**
     * Execute audits from profile policies and present the results as a Report object.
     */
    public function create(
        Profile $profile,
        TargetInterface $target
    ):Report {
        $contexts = $this->buildContexts($target);

        $start = time();
        $report = new Report(
            uri: $target->uri,
            results: $this->auditPolicies($contexts, $target, $profile->reportingPeriodStart, $profile->reportingPeriodEnd, ...$profile->dependencies),
            type: ReportType::DEPENDENCIES,
            profile: $profile,
            target: $target,
            timing: time() - $start,
            language: $this->languageManager->getCurrentLanguage(),
        );

        $report = !$report->successful ? $report : new Report(
            uri: $target->uri,
            results: $this->auditPolicies($contexts, $target, $profile->reportingPeriodStart, $profile->reportingPeriodEnd, ...$profile->policies),
            type: ReportType::ASSESSMENT,
            profile: $profile,
            target: $target,
            timing: time() - $start,
            language: $this->languageManager->getCurrentLanguage(),
        );

        // Validate the number of results reflects the number of policies that were
        // provided by the profile.
        $expected_results = count($report->type == ReportType::ASSESSMENT ? $profile->policies : $profile->dependencies);
        if (count($report->results) != $expected_results) {
            throw new Exception('Incorrect number of ' . $report->type->value . ' report results: ' . count($report->results) . '. Expecting: ' . $expected_results);
        }

        $this->eventDispatcher->dispatch($report, 'report.create');

        return $report;
    }

    private function streamAudit(array $contexts, Profile $profile, TargetInterface $target, PolicyDefinition ...$definitions): ProcessManager
    {
        $processManager = new ProcessManager($this->logger, $this->settings);
        $audit_groups = [];
        $early_results = [];
        // Dependencies must be met for a policy to be audited.
        // If they generate a response then that response is used
        // as the policy's AuditResponse and the policy is omitted from auditing.
        foreach ($definitions as $definition) {
            $policy = $definition->getPolicy($this->policyFactory);
            if ($response = $this->getDependencyResponse($contexts, $policy)) {
                $early_results[] = $response;
                continue;
            }
            $audit_groups[$policy->class][$policy->name] = $definition;
        }
        
        $errors = [];

        $target_filepath = TargetExport::create($target)->toTemporaryFile();

        foreach ($audit_groups as $class => $policies) {
            $batch = [];
            $audit = $this->auditFactory->mock($class, $target);
            $audit->setReportingPeriod($profile->reportingPeriodStart, $profile->reportingPeriodEnd);

            /**
             * @var \Drutiny\Profile\PolicyDefinition
             */
            foreach ($policies as $policy) {
                $batch_id = $this->prepareAudit($audit, $policy->getPolicy($this->policyFactory), $errors);
                if (is_string($batch_id)) {
                    $batch[$batch_id][$policy->name] = $policy;
                } elseif (is_null($batch_id)) {
                    $batch[] = [$policy->name => $policy];
                }
            }

            foreach ($batch as $batch_id => $policies) {
                $opts = array_values(array_map(fn(PolicyDefinition $p) => '--policy-definition=' . base64_encode(serialize($p)), $policies));
                $opts[] = '--reporting-period-start=' . $profile->reportingPeriodStart->format('Y-m-d H:i:s');
                $opts[] = '--reporting-period-end=' . $profile->reportingPeriodEnd->format('Y-m-d H:i:s');
                $opts[] = '--reporting-timezone=' . $profile->reportingPeriodStart->format('e');
                $opts[] = '--language=' . $profile->language;
                $opts[] = '--no-ansi';
                $opts[] = '--dtag=' . $profile->name;

                $process = $processManager->create(['policy:audit:batch', $target_filepath, ...$opts]);

                $keys = array_keys($policies);
                $name = array_shift($keys);
                if (count($keys)) {
                    $name .= ' and ' . count($keys) . ' more';
                }

                $processManager->add(
                    process: $process,
                    name: $name
                );
            }
            $processManager->update();
        }

        $processManager->then(function (array $procs) {
            return array_map(function (Process $proc) {
                $response = unserialize(base64_decode($proc->getOutput()));

                if ($response == false) {
                    $this->logger->critical(sprintf('Command %s failed: %s', $proc->getCommandLine(), $proc->getOutput()));
                    return [];
                }
                return $response;
            }, $procs);
        })->then(function (array $result_sets) {
            return call_user_func_array('array_merge', array_values($result_sets));
        });

        return $processManager;
    }

    public function promise(Profile $profile, TargetInterface $target):Report|ProcessManager
    {
        $contexts = $this->buildContexts($target);

        $start = time();
        $report = new Report(
            uri: $target->uri,
            results: $this->auditPolicies($contexts, $target, $profile->reportingPeriodStart, $profile->reportingPeriodEnd, ...$profile->dependencies),
            type: ReportType::DEPENDENCIES,
            profile: $profile,
            target: $target,
            timing: time() - $start,
            language: $this->languageManager->getCurrentLanguage(),
        );

        if (!$report->successful) {
            $this->eventDispatcher->dispatch($report, 'report.create');
            return $report;
        }

        $report = !$report->successful ? $report : new Report(
            uri: $target->uri,
            type: ReportType::ASSESSMENT,
            profile: $profile,
            target: $target,
            timing: time() - $start,
            language: $this->languageManager->getCurrentLanguage(),
        );

        $processManager = $this->streamAudit($contexts, $profile, $target, ...$profile->policies);
        $processManager->then(function (array $results) use ($report) {
            $new_report = $report->with(results: $results);
            $this->eventDispatcher->dispatch($new_report, 'report.create');
            return $new_report;
        });

        return $processManager;
    }

    /**
     * Audit a batch of policies synchonously against a common target.
     *
     * @return \Drutiny\AuditResponse\AuditResponse[]
     */
    private function auditPolicies(array $contexts, TargetInterface $target, DateTime $start, DateTime $end, PolicyDefinition ...$definitions):array
    {

        $batch = [];
        $results = [];

        // Dependencies must be met for a policy to be audited.
        // If they generate a response then that response is used
        // as the policy's AuditResponse and the policy is omitted from auditing.
        foreach ($definitions as $definition) {
            $policy = $definition->getPolicy($this->policyFactory);
            if ($response = $this->getDependencyResponse($contexts, $policy)) {
                $results[] = $response;
                continue;
            }
            $batch[$policy->class][$policy->name] = $policy;
        }
        
        $errors = [];

        // Batch audit policies by common classes. This allows audit classes
        // to optimize their auditing by the batch of policies being audited.
        foreach ($batch as $class => $policies) {
            $audit = $this->auditFactory->mock($class, $target);
            $audit->setReportingPeriod($start, $end);
            foreach ($policies as $policy) {
                try {
                    if ($this->prepareAudit($audit, $policy, $errors) === false) {
                        continue;
                    }
                    $this->logger->info("Auditing $policy->title");
                    $response = $this->policyAudit($policy, $audit, $start, $end);
                } catch (Error|Exception $e) {
                    $response = $this->handleError($e, $policy);
                } finally {
                    $this->eventDispatcher->dispatch($response, 'policy.audit.response');
                }
                $results[] = $response;
            }
        }

        $returned = count($results);
        $total = count($definitions);
        $error_count = count($errors);
        $this->logger->info("Assessment returned $returned/$total with $error_count errors.");
        
        // Merge results and errors together as result set.
        return array_merge($errors, $results);
    }

    /**
     * Prepare an audit with a given policy.
     *
     * @param \Drutiny\AuditResponse[] $errors
     *
     * @return null means there was no batching suggested for the policy.
     * @return string is an identifier to batch the policy with.
     * @return false is an error and the policy should not be processed further.
     */
    private function prepareAudit(AuditInterface $audit, Policy $policy, array &$errors):null|string|bool
    {
        try {
            return $audit->prepare($policy);
        } catch (AuditException $e) {
            $errors[] = new AuditResponse(
                policy: $policy,
                state: $e->state,
                tokens: [
                    'exception' => $e->getMessage(),
                    'exception_type' => get_class($e),
                ],
                timing: 0,
                timestamp: 0
            );
            return false;
        }
        return null;
    }

    /**
     * Audit a policy.
     */
    private function policyAudit(Policy $policy, AuditInterface $audit, DateTimeInterface $start, DateTimeInterface $end): AuditResponse
    {
        $audit->setParameter('reporting_period_start', $start);
        $audit->setParameter('reporting_period_end', $end);
        return $audit->execute($policy);
    }

    /**
     * Handle and error occuring when trying to build an AuditResponse.
     */
    private function handleError(Error|Exception $e, Policy $policy):AuditResponse
    {
        $response = new AuditResponse(
            policy: $policy,
            state: State::ERROR,
            tokens: [
                'exception' => $e->getMessage(),
                'exception_type' => get_class($e),
            ],
            timing: 0,
            timestamp: 0
        );
        $this->eventDispatcher->dispatch($response, 'policy.audit.response');
        return $response;
    }

    /**
     * Return the dependency behaviour based on their evaluation.
     */
    private function getDependencyResponse(array $contexts, Policy $policy):false|AuditResponse
    {
        $onFail = DependencyBehaviour::PASS;
        $exception = '';
        $start = time();
        foreach ($policy->depends as $dependency) {
            try {
                $this->requireDependency($dependency, $contexts);
            } catch (DependencyException $e) {
                $onFail = $onFail->higher($dependency->onFail);
                if ($onFail === $dependency->onFail) {
                    $exception = $e->getMessage();
                }
            }
        }
        return $onFail === DependencyBehaviour::PASS ? false : new AuditResponse(
            policy: $policy,
            state: State::from($onFail->getAuditOutcome()),
            tokens: [
                'exception' => $exception,
                'exception_type' => DependencyException::class
            ],
            timestamp: $start,
            timing: time() - $start
        );
    }

    /**
     * @throws DependencyException when dependency is not met.
     */
    private function requireDependency(Dependency $dependency, array $contexts):bool
    {
        $contexts['dependency'] = $dependency;
        try {
            $return = $this->syntaxProcessor->evaluate(
                expression: $this->syntaxProcessor->interpolate($dependency->expression, $contexts),
                language: $dependency->syntax,
                contexts: $contexts
            );
            if (($return === 1) || ($return === true)) {
                return true;
            }
        } catch (RuntimeError $e) {
            throw new DependencyException($dependency, $e->getMessage(), $e);
        } catch (UnexpectedValueException $e) {
            throw new DependencyException($dependency, $e->getMessage(), $e);
        }
        // UnexpectedValueException
        throw new DependencyException($dependency);
    }

    /**
     * Get contexts for SyntaxProcessor.
     */
    private function buildContexts(TargetInterface $target):array
    {
        $contexts = [];
        $contexts['target'] = $target;
        foreach ($target->getPropertyList() as $key) {
            $contexts[$key] = $target->getProperty($key);
        }
        foreach (State::cases() as $state) {
            $contexts[$state->name] = $state->value;
        }
        return $contexts;
    }
}
