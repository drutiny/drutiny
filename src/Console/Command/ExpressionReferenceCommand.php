<?php

namespace Drutiny\Console\Command;

use Drutiny\DependencyInjection\TwigEvaluatorPass;
use Drutiny\Settings;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *
 */
class ExpressionReferenceCommand extends DrutinyBaseCommand
{
    public function __construct(
        protected Settings $settings,
        protected LoggerInterface $logger
    ) {
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('expression:reference')
        ->setDescription('Display available functions and flags for use in expressions')
        ;
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pass = new TwigEvaluatorPass();
        $rows = [];
        foreach ($pass->getRegistry($this->settings) as $namespace => $functions) {
            foreach ($functions as $function => $meta) {
                $definition = "$namespace.$function";
                if (isset($meta['arguments'])) {
                    $definition .= '('.implode(', ', $meta['arguments']).')';
                }
                $rows[] = [$definition, $meta['return'] ?? 'unknown', $meta['description']];
            }
            $rows[] = new TableSeparator();
        }
        array_pop($rows);

        $io = new SymfonyStyle($input, $output);
        $io->title('Expression reference');
        $io->table(['Function/Flag', 'Return type', 'Description'], $rows);
        return 0;
    }
}
