<?php

namespace Drutiny;

use Drutiny\Audit\AuditInterface;
use Drutiny\Audit\AuditValidationException;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Entity\DataBag;
use Drutiny\Policy\DependencyException;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Target\NoSuchPropertyException;
use Drutiny\Target\TargetInterface;
use Drutiny\Upgrade\AuditUpgrade;
use Drutiny\Entity\Exception\DataNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Yaml\Yaml;
use Twig\Error\RuntimeError;

/**
 * Base class for Audit.
 */
abstract class Audit implements AuditInterface
{
    protected InputDefinition $definition;
    protected LoggerInterface $logger;
    protected ContainerInterface $container;
    protected TargetInterface $target;
    protected ExpressionLanguage $expressionLanguage;
    protected DataBag $dataBag;
    protected Policy $policy;
    protected ProgressBar $progressBar;
    protected bool $deprecated = false;
    private CacheInterface $cache;

    final public function __construct(
      ContainerInterface $container,
      TargetInterface $target,
      LoggerInterface $logger,
      ExpressionLanguage $expressionLanguage,
      ProgressBar $progressBar,
      CacheInterface $cache
      ) {
        $this->container = $container;
        $this->target = $target;
        if (method_exists($logger, 'withName')) {
          $logger = $logger->withName('audit');
        }
        $this->logger = $logger;
        $this->definition = new InputDefinition();
        $this->expressionLanguage = $expressionLanguage;
        $this->progressBar = $progressBar;
        $this->dataBag = new DataBag();
        $this->dataBag->add([
        'parameters' => new DataBag(),
      ]);
        $this->cache = $cache;
        $this->configure();
    }

    public function configure()
    {
    }

    /**
     * @return
     */
    abstract public function audit(Sandbox $sandbox);

    protected function getPolicy()
    {
        return $this->policy;
    }

    /**
     * Validate the contexts of the audit and target,
     */
    protected function validate():bool
    {
        return true;
    }

    /**
     * @param Policy $policy
     * @param bool $remediate (@deprecated)
     *
     * @return AuditResponse
     *
     * @throws \Drutiny\Audit\AuditValidationException
     */
    final public function execute(Policy $policy, $remediate = false):AuditResponse
    {
        if ($this->deprecated) {
          $this->logger->warning(sprintf("Policy '%s' is using '%s' which is a deprecated class. This may fail in the future.", $policy->name, get_class($this)));
        }
        $this->policy = $policy;
        $response = new AuditResponse($policy);
        $execution_start_time = new \DateTime();
        $this->logger->info('Auditing '.$policy->name.' with '.get_class($this));
        $outcome = AuditInterface::ERROR;
        try {
            if (!$this->validate()) {
              throw new AuditValidationException("Target of type ".get_class($this->target)." is not suitable for audit class ".get_class($this). " with policy: ".$policy->name);
            }

            $dependencies = $policy->getDepends();
            $this->progressBar->setMaxSteps(count($dependencies) + $this->progressBar->getMaxSteps());
            // Ensure policy dependencies are met.
            foreach ($policy->getDepends() as $dependency) {
                // Throws DependencyException if dependency is not met.
                $dependency->execute($this);
                $this->progressBar->advance();
            }

            // Build parameters to be used in the audit.
            foreach ($policy->build_parameters ?? [] as $key => $value) {
              try {
                $this->logger->debug(__CLASS__ . ':build_parameters('.$key.'): ' . $value);
                $value = $this->evaluate($value, 'twig');

                // Set the token to be available for other build_parameters.
                $this->set($key, $value);

                // Set the parameter to be available in the audit().
                if ($this->definition->hasArgument($key)) {
                  $policy->addParameter($key, $value);
                }
              }
              catch (RuntimeError $e)
              {
                throw new \Exception("Failed to create key: $key. Encountered Twig runtime error: " . $e->getMessage());
              }
            }

            $input = new ArrayInput($policy->getAllParameters(), $this->definition);
            $this->dataBag->get('parameters')->add($input->getArguments());
            $this->dataBag->add($input->getArguments());

            // Run the audit over the policy.
            $outcome = $this->audit(new Sandbox($this));
        } catch (DependencyException $e) {
            $outcome = AuditInterface::ERROR;
            $outcome = $e->getDependency()->getFailBehaviour();
            $message = $e->getMessage();
            $this->set('exception', $message);
            $this->set('exception_type', get_class($e));
            $this->logger->warning("'{policy}' {class} ({uri}): $message", [
              'class' => get_class($this),
              'uri' => $this->target->getUri(),
              'policy' => $policy->name
            ]);
        } catch (AuditValidationException $e) {
            $outcome = AuditInterface::NOT_APPLICABLE;
            $message = $e->getMessage();
            $this->set('exception', $message);
            $this->set('exception_type', get_class($e));
            $this->logger->warning("'{policy}' {class} ({uri}): $message", [
              'class' => get_class($this),
              'uri' => $this->target->getUri(),
              'policy' => $policy->name
            ]);
        } catch (NoSuchPropertyException $e) {
            $outcome = AuditInterface::NOT_APPLICABLE;
            $message = $e->getMessage();
            $this->set('exception', $message);
            $this->set('exception_type', get_class($e));
            $this->logger->warning("'{policy}' {class} ({uri}): $message", [
              'class' => get_class($this),
              'uri' => $this->target->getUri(),
              'policy' => $policy->name
            ]);
        } catch (InvalidArgumentException $e) {
            $outcome = AuditInterface::ERROR;
            $this->set('exception_type', get_class($e));
            $message = $e->getMessage();
            $this->set('exception', $message);
            $this->logger->warning("'{policy}' {class} ({uri}): $message", [
              'class' => get_class($this),
              'uri' => $this->target->getUri(),
              'policy' => $policy->name
            ]);
            $this->logger->warning($e->getTraceAsString());
            $this->logger->warning($policy->name . ': ' . get_class($this));
            $this->logger->warning(print_r($policy->getAllParameters(), 1));

            $helper = AuditUpgrade::fromAudit($this);
            $helper->addParameterFromException($e);
            $this->set('exception', $helper->getParamUpgradeMessage());
        } catch (\Exception $e) {
            $outcome = AuditInterface::ERROR;
            $message = $e->getMessage();
            if ($this->container->get('output')->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
                $message .= PHP_EOL.$e->getTraceAsString();
            }
            $this->set('exception', $message);
            $this->set('exception_type', get_class($e));
            $this->logger->error("'{policy}' {class} ({uri}): $message", [
              'class' => get_class($this),
              'uri' => $this->target->getUri(),
              'policy' => $policy->name
            ]);
        } finally {
            // Log the parameters output.
            $tokens = $this->dataBag->export();
            $this->logger->debug("Tokens:\n".Yaml::dump($tokens, 4, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
            // $this->logger->debug("Parameters:\n" . Yaml::dump($this->dataBag->get('parameters')->all(), 4, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
            // Set the response.
            $response->set($outcome ?? AuditInterface::ERROR, $tokens);
        }
        $execution_end_time = new \DateTime();
        $total_execution_time = $execution_start_time->diff($execution_end_time);
        $this->logger->info($total_execution_time->format('Execution completed for policy "' . $policy->name . '" in %m month(s) %d day(s) %H hour(s) %i minute(s) %s second(s)'));
        return $response;
    }

    /**
     * Use a new Audit instance to audit policy.
     *
     * @param string $policy_name
     *    The name of the policy to audit.
     */
    public function withPolicy(string $policy_name):AuditResponse
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
    public function evaluate(string $expression, $language = 'expression_language', array $contexts = [])
    {
        try {
          $contexts = array_merge($contexts, $this->getContexts());
          switch ($language) {
            case 'twig':
              return $this->evaluateTwigSyntax($expression, $contexts);
            case 'expression_language':
            default:
              return $this->expressionLanguage->evaluate($expression, $contexts);
          }
        }
        catch (\Exception $e) {
          $this->logger->error("Evaluation failure {syntax}: {expression}: {message}", [
            'syntax' => $language,
            'expression' => $expression,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
          ]);
          throw $e;
        }
    }

    /**
     * Evaluate a twig expression.
     */
    private function evaluateTwigSyntax(string $expression, array $contexts = [])
    {
        $code = '{{ ('.$expression.')|json_encode()|raw }}';
        $twig = $this->container->get('Twig\Environment');
        $template = $twig->createTemplate($code);
        $output = $twig->render($template, $contexts);
        return json_decode($output, true);
    }

    /**
     * Allow strings to utilise Audit and Target contexts.
     */
    public function interpolate(string $string, array $contexts = []): string
    {
        return $this->_interpolate($string, array_merge($contexts, $this->getContexts()));
    }

    /**
     * Helper function for the public interpolate function.
     */
    private function _interpolate(string $string, iterable $vars, $key_prefix = ''): string
    {
        foreach ($vars as $key => $value) {
            if (is_iterable($value)) {
                $string = $this->_interpolate($string, $value, $key.'.');
            }

            $token = '{'.$key_prefix.$key.'}';
            if (false === strpos($string, $token)) {
                continue;
            }

            $value = (string) $value;
            $string = str_replace($token, $value, $string);
        }

        return $string;
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
        }
        catch (DataNotFoundException $e) {
            return $default_value;
        }
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

    public function get(string $name)
    {
        return $this->dataBag->get($name);
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

    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * Set information about a parameter.
     *
     * This is used exclusively when the configure() method is called.
     * This allows the audit to specify and validate inputs from a policy.
     */
    protected function addParameter(string $name, int $mode = null, string $description = '', $default = null): AuditInterface
    {
        if (!isset($this->definition)) {
            $this->definition = new InputDefinition();
        }
        $args = $this->definition->getArguments();
        $input = new InputArgument($name, $mode, $description, $default);

        if ($mode == self::PARAMETER_REQUIRED) {
          array_unshift($args, $input);
        }
        else {
          $args[] = $input;
        }

        $this->definition->setArguments($args);

        return $this;
    }

    /**
     * Set audit class as deprecated and shouldn't be used anymore.
     */
    protected function setDeprecated(bool $deprecated = true):AuditInterface
    {
      $this->deprecated = $deprecated;
      return $this;
    }

    public function isDeprecated():bool
    {
      return $this->deprecated;
    }

    protected function runCacheable($contexts, callable $func)
    {
      $cid = md5(get_class($this) . json_encode($contexts));
      return $this->cache->get($cid, $func);
    }
}
