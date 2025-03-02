<?php

namespace Drutiny\Audit;

use DateTimeZone;
use Error;
use Exception;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use TypeError;

/**
 * Evaluates expressions through Twig.
 */
class TwigEvaluator
{
    protected array $globalContexts = [];

    public function __construct(
        protected Environment $twig,
        protected LoggerInterface $logger,
    ) {
    }

    public function setTimezone(DateTimeZone $timezone):void
    {
        $this->twig->getExtension(CoreExtension::class)->setTimezone($timezone);
    }

    public function setContext($key, $value):self
    {
        $this->globalContexts[$key] = $value;
        return $this;
    }

    public function getGlobalContexts():array
    {
        return $this->globalContexts;
    }
    
    /**
     * Evaluate a twig expression.
     */
    public function execute(string $expression, array $contexts = []):mixed
    {
        try {
            // Remove line breaks from expression. These are only to make them easier to read
            // in YAML files and the like.
            if (strpos($expression, "\n") !== false) {
                // Remove inline comments starting with '#' and empty lines.
                $lines = explode("\n", $expression);
                $lines = array_filter($lines, fn($line) => substr(trim($line), 0, 1) != '#' && trim($line) != '');
                $expression = implode("\n", $lines);

                $expression = preg_replace('/(\n(\s*)?)/', ' ', $expression);
            }

            $code = '{{ ('.$expression.')|json_encode(constant("JSON_UNESCAPED_SLASHES") b-or constant("JSON_UNESCAPED_UNICODE"))|raw }}';
            $template = $this->twig->createTemplate($code);
            $contexts = array_merge($this->globalContexts, $contexts);
            $output = $this->twig->render($template, $contexts);
            $result = json_decode($output, true);
        } catch (TypeError $e) {
            $this->logger->error($e->getMessage());
            return null;
        }
        return $result;
    }
}
