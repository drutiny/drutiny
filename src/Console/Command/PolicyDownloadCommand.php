<?php

namespace Drutiny\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Drutiny\PolicyFactory;
use Drutiny\Profile;
use Drutiny\LanguageManager;
use Psr\Log\LoggerInterface;

/**
 *
 */
class PolicyDownloadCommand extends DrutinyBaseCommand
{

  protected $policyFactory;
  protected $languageManager;
  protected $logger;

  public function __construct(LoggerInterface $logger, PolicyFactory $factory, LanguageManager $languageManager)
  {
      $this->logger = $logger;
      $this->policyFactory = $factory;
      $this->languageManager = $languageManager;
      parent::__construct();
  }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('policy:download')
        ->setDescription('Download a remote policy locally.')
        ->addArgument(
            'policy',
            InputArgument::REQUIRED,
            'The name of the profile to download.'
        )
        ->addArgument(
            'source',
            InputArgument::OPTIONAL,
            'The source to download the profile from.'
        );
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $render = new SymfonyStyle($input, $output);
        $list = $this->policyFactory->getPolicyList();
        $keyword = strtolower($input->getArgument('policy'));
        $container = $this->getApplication()->getKernel()->getContainer();
        $directory = $container->getParameter('policy.library.fs');

        $rows = array();
        foreach ($list as $listedPolicy) {
            if (strpos(strtolower($listedPolicy['name']), $keyword) === false) {
              continue;
            }
            $policy = $this->policyFactory->loadPolicyByName($listedPolicy['name']);
            $name = str_replace(':', '-', $policy->name);
            $filename = $directory . "/$name.policy.yml";

            if (!file_exists($directory) && !mkdir($directory)) {
                $render->error("Cannot download into $directory: directory doesn't exist and can't be created.");
                return 1;
            }
            if (file_exists($filename)) {
                $render->error("$filename already exists. Please delete this file if you wish to download it from its source.");
                continue;
            }

            $output = Yaml::dump($policy->export(), 6, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

            file_put_contents($filename, $output);
            $render->success("$filename written.");
        }
        $this->getPolicyFactory()->getSource('localfs')->refresh();
        return 0;
    }
}
