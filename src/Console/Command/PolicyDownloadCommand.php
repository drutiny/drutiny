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
use Drutiny\Settings;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 *
 */
class PolicyDownloadCommand extends DrutinyBaseCommand
{
    use LanguageCommandTrait;

    public function __construct(
        protected LoggerInterface $logger,
        protected PolicyFactory $policyFactory,
        protected LanguageManager $languageManager,
        protected Settings $settings
    ) {
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
        $this->configureLanguage();
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLanguage($input);
        $render = new SymfonyStyle($input, $output);
        $directory = (array) $this->settings->get('policy.library.fs');
        $directory = array_shift($directory);

        $source = $this->policyFactory->getSource($this->getSource($input, $output));
        $policyList = $source->getList($this->languageManager);

        if (!isset($policyList[$input->getArgument('policy')])) {
            $render->error("Policy does not exist in source.");
            return 1;
        }

        $policy = $source->load($policyList[$input->getArgument('policy')]);

        $name = str_replace(':', '-', $policy->name);
        $filename = $directory . "/$name.policy.yml";

        if (!file_exists($directory) && !mkdir($directory)) {
            $render->error("Cannot download into $directory: directory doesn't exist and can't be created.");
            return 1;
        }
        if (file_exists($filename)) {
            $render->error("$filename already exists. Please delete this file if you wish to download it from its source.");
            return 2;
        }

        $export = $policy->export();

        $remove_keys = ['uri', 'weight', 'source'];
        $commentary = ['This policy was downloaded using policy:download command.'];
        foreach ($export as $key => $value) {
            if (in_array($key, $remove_keys) || empty($value)) {
                if (in_array($key, $remove_keys)) {
                    $commentary[] = "Original $key: $value";
                }
                unset($export[$key]);
            }
        }

        $key_order = [
            'title', 'name', 'uuid', 'class', 'description', 'language',
            'tags', 'severity', 'type',
            'depends', 'build_parameters', 'parameters',
            'success', 'failure', 'remediation', 'warning',
            'chart'
        ];

        $yaml = [];
        // Set the YAML file in a given order.
        foreach ($key_order as $key) {
            if (isset($export[$key])) {
                $yaml[$key] = $export[$key];
            }
        }

        // Catch all, add any missed fields.
        foreach ($export as $key => $value) {
            $yaml[$key] ??= $export[$key];
        }

        $output = Yaml::dump($yaml, 6, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        $commentary = '# ' . implode("\n# ", $commentary) . "\n";
        file_put_contents($filename, $commentary.$output);
        $render->success(realpath($filename) .      " written.");

        $this->policyFactory->getSource('localfs')->refresh($this->languageManager);
        return 0;
    }

    protected function getSource(InputInterface $input, OutputInterface $output)
    {
        $source = $input->getArgument('source');
        if (!empty($source)) {
            return $source;
        }
        $render = new SymfonyStyle($input, $output);
        
        $sources = [];
        foreach ($this->policyFactory->sources as $source) {
            $source = $this->policyFactory->getSource($source->name);
            $list = $source->getList($this->languageManager);
            $sources[$source->name] = $list[$input->getArgument('policy')] ?? false;
        }

        $choices = array_keys(array_filter($sources));

        if (empty($choices)) {
            throw new InvalidArgumentException($input->getArgument('policy') . ' could not be found.');
        } elseif (count($choices) > 1) {
            $choice = $render->choice("Which source would you like to download the policy from?", $choices);
        } elseif (!$render->confirm("Download ".$input->getArgument('policy')." from {$choices[0]}?")) {
            throw new Exception("Opted not to download policy.");
        } else {
            $choice = 0;
        }

        return is_string($choice) ? $choice : $choices[$choice];
    }
}
