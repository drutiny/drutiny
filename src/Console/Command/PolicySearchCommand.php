<?php

namespace Drutiny\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Drutiny\PolicyFactory;

/**
 *
 */
class PolicySearchCommand extends Command
{

    protected $policyFactory;


    public function __construct(PolicyFactory $factory)
    {
        $this->policyFactory = $factory;
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('policy:search')
        ->setDescription('List policies based on a keyword search criteria.')
        ->addArgument(
            'keyword',
            InputArgument::REQUIRED,
            'The search keyword to filter the list of policies by.'
        )
        ->addOption(
            'filter',
            't',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Filter list by tag'
        );
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $keyword = strtolower($input->getArgument('keyword'));
        $list = $this->policyFactory->getPolicyListByKeyword($keyword);

        $rows = array();
        foreach ($list as $listedPolicy) {
            $rows[] = array(
                'description' => '<options=bold>' . wordwrap($listedPolicy['title'], 50) . '</>',
                'name' => $listedPolicy['name'],
                'source' => $listedPolicy['source'],
            );
        }

        usort($rows, function ($a, $b) {
            $x = [strtolower($a['name']), strtolower($b['name'])];
            sort($x, SORT_STRING);

            return $x[0] == strtolower($a['name']) ? -1 : 1;
        });

        $io = new SymfonyStyle($input, $output);
        $io->table(['Title', 'Name', 'Source'], $rows);

        return 0;
    }

  /**
   *
   */
    protected function formatDescription($text)
    {
        $lines = explode(PHP_EOL, $text);
        $text = implode(' ', $lines);
        return wordwrap($text, 50);
    }
}
