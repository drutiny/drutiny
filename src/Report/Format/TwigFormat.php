<?php

namespace Drutiny\Report\Format;

use Drutiny\Assessment;
use Drutiny\Console\Helper\User;
use Drutiny\Report\FilesystemFormatInterface;
use Drutiny\Report\RenderedReport;
use Drutiny\Report\Report;
use Drutiny\Settings;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Extension\CoreExtension;

abstract class TwigFormat extends FilesystemFormat implements FilesystemFormatInterface
{
    public function __construct(
        protected Environment $twig,
        OutputInterface $output,
        LoggerInterface $logger
    ) {
        parent::__construct($output, $logger);
    }

    public function configure()
    {
        $this->twig->addGlobal('ext', $this->getExtension());
    }

    public function render(Report $report):RenderedReport
    {
        // Ensure Twig's timezone reflects that of the report.
        $this->twig->getExtension(CoreExtension::class)->setTimezone($report->reportingPeriodStart->getTimezone());
        try {
            $template = $this->definition->template;
          // 2.x backwards compatibility.
            if (strpos($template, '.twig') === false) {
                $this->logger->warning("Deprecated template declaration found: $template. Templates should explicitly specify extension (e.g. .md.twig).");
                $template .= '.'.$this->getExtension().'.twig';
            }
            $this->logger->debug("Rendering ".$this->getName()." with template $template.");
          
            $vars = get_object_vars($report);
          // Backward compatibility
            $vars['assessment'] = new Assessment($report);
            $vars['settings'] = $this->twig->getRuntime(Settings::class)->getAll();
            $vars['userIdentity'] = $this->twig->getRuntime(User::class)->getIdentity();
            $vars['sections'] = $this->prepareContent($vars);
            $this->buffer->write($this->twig->render($template, $vars));
        } catch (Error $e) {
            $this->logTwigError($e);
            throw $e;
        }

        return new RenderedReport($report->getName(), $this->buffer);
    }

    /**
     * Attempt to load a twig template based on the provided format extension.
     *
     * Each class implementing this one will provide a property called "extension".
     * This extension becomes apart of a prefix used to auto load twig templates.
     *
     * @param $name the namespace for the template. E.g. "content/page"
     */
    final protected function loadTwigTemplate($name)
    {
        return $this->twig->load(sprintf('%s.%s.twig', $name, $this->getExtension()));
    }

    /**
     * Produce an array of renderable sections for the twig template.
     */
    abstract protected function prepareContent(array $vars):array;


    protected function logTwigError(Error $e):void
    {
        $message[] = $e->getMessage();
        foreach ($e->getTrace() as $stack) {
            $message[] = strtr('file:line', $stack);
        }
        $this->logger->error(implode("\n", $message));
        if ($source = $e->getSourceContext()) {
            $lines = [];
            foreach (explode(PHP_EOL, $source->getCode()) as $idx => $line) {
                $lines[] = ($idx+1) . ":\t" . $line;
            }
            $this->logger->info(PHP_EOL . implode(PHP_EOL, $lines));
        }
    }
}
