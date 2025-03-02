<?php

namespace Drutiny;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Drutiny\Attribute\Deprecated;
use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit\AuditInterface;
use Drutiny\Audit\AuditValidationException;
use Drutiny\Audit\Exception\AuditException;
use Drutiny\Audit\InputDefinition;
use Drutiny\Audit\SyntaxProcessor;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\AuditResponse\State;
use Drutiny\Entity\DataBag;
use Drutiny\Policy\DependencyException;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Target\TargetInterface;
use Drutiny\Upgrade\AuditUpgrade;
use Drutiny\Entity\Exception\DataNotFoundException;
use Drutiny\Policy\Compatibility\PolicyCompatibilityException;
use Drutiny\Policy\Dependency;
use Drutiny\Sandbox\ReportingPeriodTrait;
use Drutiny\Target\TargetPropertyException;
use Error;
use Exception;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Reflection;
use ReflectionClass;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Yaml\Yaml;
use Twig\Error\RuntimeError;

/**
 * Base class for Audit.
 */
abstract class Audit implements AuditInterface
{
    use ReportingPeriodTrait;
    
    protected DataBag $dataBag;
    protected Policy $policy;
    private InputDefinition $definition;
    public DateTimeInterface $dateTime;
    protected bool $deprecated = false;
    protected string $deprecationMessage = '';
    protected int $verbosity;

    final public function __construct(
        protected ContainerInterface $container,
        protected TargetInterface $target,
        protected LoggerInterface $logger,
        protected SyntaxProcessor $syntaxProcessor,
        protected ProgressBar $progressBar,
        protected CacheInterface $cache,
        protected EventDispatcher $eventDispatcher,
        OutputInterface $output,
    ) {
        if ($logger instanceof Logger) {
            $this->logger = $logger->withName('audit');
        }
        $this->definition = new InputDefinition();
        $this->resetDataBag();
        $this->registerParameters();
        $this->configure();
        $this->verbosity = $output->getVerbosity();

        $reflect = new ReflectionClass($this);
        $attrs = $reflect->getAttributes(Deprecated::class);
        $this->deprecated = !empty($attrs);
    }

    /**
     * Reset the databag.
     *
     * This should happen every time an policy is audited.
     */
    protected function resetDataBag(): void
    {
        $this->dataBag = new DataBag();
        $this->dataBag->add([
            'parameters' => new DataBag(),
        ]);
    }

    /**
     * Load parameters from PHP attributes on this class and parent classes.
     */
    private function registerParameters(): void
    {
        $reflection = new ReflectionClass($this);
        do {
            foreach ($reflection->getAttributes(Parameter::class) as $attr) {
                $parameter = $attr->newInstance()->fromClass($reflection->getName());

                // Don't let parent classes override parameters from child classes.
                if ($this->definition->hasParameter($parameter->name)) {
                    continue;
                }
                $this->definition->addParameter($parameter);
            }
        } while ($reflection = $reflection->getParentClass());
    }

    /**
     * {@inheritdoc}
     */
    public function configure():void
    {
    }

    /**
     * @return
     */
    abstract public function audit(Sandbox $sandbox);

    protected function getPolicy():Policy
    {
        return $this->policy;
    }

    /**
     * Validate the contexts of the audit and target,
     *
     * @deprecated use Drutiny\Policy\Dependency attributes instead.
     */
    protected function validate(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(Policy $policy):null|string
    {
        // By default, policies don't get batched together.
        // An audit may override this method to get a different
        // batching string that allow policies to be batch audited.
        // This is typically done to make the auditing process faster.
        return $policy->name;
    }

    /**
     * {@inheritdoc}
     */
    final public function execute(Policy $policy, $remediate = false): AuditResponse
    {
        $this->resetDataBag();

        // Ensure the syntax evaluator uses the right Timezone.
        if (isset($this->reportingPeriodStart)) {
            $this->syntaxProcessor->setTimezone($this->reportingPeriodStart->getTimezone());
        }
        
        $this->dateTime = new DateTimeImmutable();
        if ($this->deprecated) {
            $this->logger->warning(sprintf("Policy '%s' is using '%s' which is a deprecated class. This may fail in the future.", $policy->name, get_class($this)));
            if (!empty($this->deprecationMessage)) {
                $this->logger->warning("Class {class} is deprecated: {msg}", [
                  "class" => get_class($this),
                  "msg" => $this->deprecationMessage,
                ]);
            }
        }
        $this->policy = $policy;
        $this->logger->info('Auditing '.$policy->name.' with '.get_class($this));
        $outcome = AuditInterface::ERROR;
        try {
            /* @throws \Drutiny\Policy\Compatibility\PolicyCompatibilityException */
            $policy->isCompatible();

            if (!$this->validate()) {
                throw new AuditValidationException("Target of type ".get_class($this->target)." is not suitable for audit class ".get_class($this). " with policy: ".$policy->name);
            }
            
            $reflection = new ReflectionClass($this);
            // Ensure audit dependencies are met.
            foreach ($reflection->getAttributes(Dependency::class) as $attr) {
                $this->executeDependency($attr->newInstance());
            }

            $this->progressBar->setMaxSteps(count($policy->depends) + $this->progressBar->getMaxSteps());
            // Ensure policy dependencies are met.
            foreach ($policy->depends as $dependency) {
                // Throws DependencyException if dependency is not met.
                $this->executeDependency($dependency);
                // $dependency->execute($this);
                $this->progressBar->advance();
            }

            $this->policy = $this->prepareBuildParameters($policy);
            
            $parameters = $this->syntaxProcessor->processParameters($this->policy->parameters->all(), $this->getContexts(), $this->definition);
            $values = $this->definition->fromValues($parameters);
            $this->dataBag->get('parameters')->add($values);
            $this->dataBag->add($values);

            // Run the audit over the policy.
            $outcome = $this->audit(new Sandbox($this));
        } catch (PolicyCompatibilityException $e) {
            $outcome = State::ERROR->value;
            $message = $e->getMessage();
            $this->set('exception', $message);
            $this->set('exception_type', get_class($e));
            $this->logger->error("'{policy}' {class} ({uri}): $message", [
                'class' => get_class($this),
                'uri' => $this->target->getUri(),
                'policy' => $this->policy->name
            ]);
        } catch (DependencyException $e) {
            $outcome = $e->getDependency()->onFail->getAuditOutcome();
            $message = $e->getMessage();
            $this->set('exception', $message);
            $this->set('exception_type', get_class($e));
            $log_level = $outcome == AuditInterface::IRRELEVANT ? 'debug' : 'warning';
            $this->logger->log($log_level, "'{policy}' {class} ({uri}): $message", [
              'class' => get_class($this),
              'uri' => $this->target->getUri(),
              'policy' => $this->policy->name
            ]);
        } catch (AuditValidationException $e) {
            $outcome = AuditInterface::NOT_APPLICABLE;
            $message = $e->getMessage();
            $this->set('exception', $message);
            $this->set('exception_type', get_class($e));
            $this->logger->warning("'{policy}' {class} ({uri}): $message", [
              'class' => get_class($this),
              'uri' => $this->target->getUri(),
              'policy' => $this->policy->name
            ]);
        } catch (TargetPropertyException $e) {
            $outcome = AuditInterface::NOT_APPLICABLE;
            $message = $e->getMessage();
            $this->set('exception', $message);
            $this->set('exception_type', get_class($e));
            $this->logger->warning("'{policy}' {class} ({uri}): $message", [
              'class' => get_class($this),
              'uri' => $this->target->getUri(),
              'policy' => $this->policy->name
            ]);
        } catch (InvalidArgumentException $e) {
            $outcome = AuditInterface::ERROR;
            $this->set('exception_type', get_class($e));
            $message = $e->getMessage();
            $this->set('exception', $message);
            $this->logger->warning("'{policy}' {class} ({uri}): $message", [
              'class' => get_class($this),
              'uri' => $this->target->getUri(),
              'policy' => $this->policy->name
            ]);
            $this->logger->warning($e->getTraceAsString());
            $this->logger->warning($policy->name . ': ' . get_class($this));
            $this->logger->warning(print_r($policy->parameters->all(), 1));

            $helper = AuditUpgrade::fromAudit($this);
            $helper->addParameterFromException($e);
            $this->set('exception', $helper->getParamUpgradeMessage());
        } catch (Exception|Error $e) {
            $outcome = AuditInterface::ERROR;
            $message = $e->getMessage();
            if ($this->verbosity > OutputInterface::VERBOSITY_NORMAL) {
                $message .= PHP_EOL.$e->getTraceAsString();
            }
            $this->set('exception', $message);
            $this->set('exception_type', get_class($e));
            $this->set('file', $e->getFile());
            $this->set('line', $e->getLine());
            $trace = [];
            do {
                $trace[] = $e->getTraceAsString();
            } while ($e = $e->getPrevious());
            $this->set('trace', $trace);
            $this->logger->error("'{policy}' {class} ({uri}): $message", [
              'class' => get_class($this),
              'uri' => $this->target->getUri(),
              'policy' => $this->policy->name
            ]);
        } finally {
            // Log the parameters output.
            $tokens = json_decode(json_encode($this->dataBag->export()), true);
            $this->logger->debug("Tokens:\n".Yaml::dump($tokens, 4, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
            // Set the response.
            $response = new AuditResponse(
                policy: $this->policy,
                state: State::from((int) $outcome ?? State::ERROR)->withPolicyType($this->policy->type),
                tokens: $tokens,
                timestamp: (int) $this->dateTime->format('U'),
                timing: time() - $this->dateTime->format('U')
            );

            $this->eventDispatcher->dispatch(new GenericEvent('audit', [
              'class' => get_class($this),
              'policy' => $this->policy->name,
              'outcome' => $response->getType(),
              'uri' => $this->target->getUri(),
            ]), 'audit');
        }
        $total_execution_time = $this->dateTime->diff(new DateTime());
        $this->logger->info($total_execution_time->format('Execution completed for policy "' . $this->policy->name . '" in %m month(s) %d day(s) %H hour(s) %i minute(s) %s second(s)'));
        return $response;
    }

    /**
     * Prepare build parameters.
     */
    protected function prepareBuildParameters(Policy $policy): Policy
    {
        // Build parameters to be used in the audit.
        foreach ($policy->build_parameters->all() as $key => $value) {
            try {
                // Use SyntaxProcessor to process build_parameters if the key denotes
                // it should be processed.
                if (($processed_key = $this->syntaxProcessor->processParameterName($key)) != $key) {
                    $value = $this->syntaxProcessor->processParameter($key, $value, $this->getContexts());
                    $key = $processed_key;
                }
                // Otherwise fallback to standard twig evaluation.
                else {
                    $this->logger->debug(__CLASS__ . ':build_parameters('.$key.'): ' . $value);
                    $value = $this->evaluate($value, 'twig');
                }

                // Set the token to be available for other build_parameters.
                $this->set($key, $value);

                // Set the parameter to be available in the audit().
                if ($this->definition->hasParameter($key)) {
                    $parameters = $policy->parameters->all();
                    $parameters[$key] = $value;
                    $policy = $policy->with(parameters: $parameters);
                }
            } catch (RuntimeError $e) {
                throw new AuditException("Failed to create key: $key. Encountered Twig runtime error: " . $e->getMessage());
            }
        }
        return $policy;
    }

    /**
     * Evaluate a dependency.
     *
     * @throws DependencyException.
     */
    protected function executeDependency(Dependency $dependency):bool
    {
        try {
            $expression = $this->interpolate($dependency->expression);
            $return = $this->evaluate($expression, $dependency->syntax, [
                'dependency' => $dependency
            ]);
            if ($return === 1 || $return === true) {
                return true;
            }
        } catch (\Exception $e) {
            $this->logger->warning($dependency->syntax . ': ' . $e->getMessage());
        }
        $this->logger->debug('Expression FAILED.', [
            'class' => get_class($this),
            'expression' => $expression,
            'return' => print_r($return ?? 'EXCEPTION_THROWN', 1),
            'syntax' => $dependency->syntax
        ]);
        // Execute the on fail behaviour.
        throw new DependencyException($dependency);
    }
        

    /**
     * Use a new Audit instance to audit policy.
     *
     * @param string $policy_name
     *    The name of the policy to audit.
     */
    public function withPolicy(string $policy_name): AuditResponse
    {
        $this->logger->debug("->withPolicy($policy_name)");
        $policy = $this->container
            ->get('policy.factory')
            ->loadPolicyByName($policy_name);
        return $this->container->get($policy->class)->execute($policy);
    }

    /**
     * Evaluate an expression using the Symfony ExpressionLanguage engine.
     */
    public function evaluate(string $expression, $language = 'expression_language', array $contexts = []):mixed
    {
        return $this->syntaxProcessor->evaluate($expression, $language, array_merge($contexts, $this->getContexts()));
    }

    /**
     * Allow strings to utilise Audit and Target contexts.
     */
    public function interpolate(string $string, array $contexts = []): string
    {
        return $this->syntaxProcessor->interpolate($string, array_merge($contexts, $this->getContexts()));
    }

    /**
     * Get all contexts from the Audit class.
     */
    protected function getContexts(): array
    {
        $contexts = $this->dataBag->all();
        $contexts['target'] = $this->target;
        foreach ($this->target->getPropertyList() as $key) {
            $contexts[$key] = $this->target->getProperty($key);
        }

        $reflection = new \ReflectionClass(__CLASS__);
        foreach ($reflection->getConstants() as $key => $value) {
            $contexts[$key] = $value;
        }

        $contexts['audit'] = $this;

        return $contexts;
    }

    /**
     * Know if a parameter exists in the databag.
     *
     * To know if the parameter exists in the audit definition,
     * use Audit::getDefinition()->hasParameter($name).
     */
    public function hasParameter(string $name): bool
    {
        return $this->dataBag->get('parameters')->has($name);
    }

    /**
     * Set a parameter. Typically provided by a policy.
     */
    public function setParameter(string $name, $value): AuditInterface
    {
        $this->dataBag->get('parameters')->set($name, $value);
        return $this;
    }

    /**
     * Get a set parameter or provide the default value.
     */
    public function getParameter(string $name, $default_value = null)
    {
        try {
            return $this->dataBag->get('parameters')->get($name) ?? $default_value;
        } catch (DataNotFoundException $e) {
            return $this->definition->getParameter($name)->default ?? $default_value;
        }
    }

    /**
     * Get all available parameters.
     */
    public function getAllParameters():array
    {
        return $this->dataBag->get('parameters')->all();
    }

    /**
     * Set a non-parameterized value such as a token.
     *
     * This function is used to communicate output data computed by the
     * audit class. This is useful for policies to use to contextualize
     * messaging.
     */
    public function set(string $name, $value): AuditInterface
    {
        $this->dataBag->set($name, $value);

        return $this;
    }

    /**
     * Get a token value.
     */
    public function get(string $name)
    {
        return $this->dataBag->get($name);
    }

    /**
     * Check if an Audit has a given argument.
     *
     * @deprecated use getDefinition()->hasParameter($name) instead.
     */
    public function hasArgument(string $name): bool
    {
        return $this->definition->hasParameter($name);
    }

    /**
     * Used to provide target to deprecated Sandbox object.
     *
     * @deprecated
     */
    public function getTarget(): TargetInterface
    {
        return $this->target;
    }

    /**
     * Used to provide logger to deprecated Sandbox object.
     *
     * @deprecated
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getDefinition():InputDefinition
    {
        return $this->definition;
    }

    /**
     * Set information about a parameter.
     *
     * This is used exclusively when the configure() method is called.
     * This allows the audit to specify and validate inputs from a policy.
     */
    protected function addParameter(string $name, int|null $mode = null, string $description = '', $default = null, ?Type $type = null): self
    {
        $this->definition->addParameter(new Parameter(
            name: $name,
            mode: $mode ?? Parameter::OPTIONAL,
            description: $description,
            default: $default,
            type: $type,
            class: $this,
        ));
        return $this;
    }

    /**
     * Set audit class as deprecated and shouldn't be used anymore.
     */
    protected function setDeprecated(string $message = ''): self
    {
        $this->deprecated = true;
        $this->deprecationMessage = $message;
        return $this;
    }

    public function isDeprecated(): bool
    {
        return $this->deprecated;
    }

    protected function runCacheable($contexts, callable $func)
    {
        $cid = md5(get_class($this) . json_encode($contexts));
        return $this->cache->get($cid, $func);
    }
}
