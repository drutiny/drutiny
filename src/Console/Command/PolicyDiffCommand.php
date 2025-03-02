<?php

namespace Drutiny\Console\Command;

use Drutiny\LanguageManager;
use Drutiny\Policy;
use Drutiny\PolicyFactory;
use Drutiny\Profile;
use Drutiny\ProfileFactory;
use Fiasco\SymfonyConsoleStyleMarkdown\Renderer;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 *
 */
class PolicyDiffCommand extends DrutinyBaseCommand
{

    public function __construct(
        protected Environment $twigEnvironment,
        protected PolicyFactory $policyFactory,
        protected ProfileFactory $profileFactory,
        protected LanguageManager $languageManager
    ) {
        parent::__construct();
    }

    use LanguageCommandTrait;

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('policy:diff')
        ->setDescription('Show a diff of a common policy from difference sources.')
        ->addArgument(
            'policy',
            InputArgument::REQUIRED,
            'The name of the policy to diff.'
        )
        ->addArgument(
            'source1',
            InputArgument::OPTIONAL,
            'The name of the source to load the original policy from.'
        )
        ->addArgument(
            'source2',
            InputArgument::OPTIONAL,
            'The name of the source to load the comparative policy from.'
        );
        $this->configureLanguage();
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        
        // Set global language used by policy/profile sources.
        $this->initLanguage($input);

        $policy_name = $input->getArgument('policy');

        if ($input->getArgument('source1') === null || $input->getArgument('source2') === null) {
            $policy_sources = $this->getSourcesByPolicyName($policy_name);
            if (empty($policy_sources)) {
                throw new LogicException("There are no sources found for policy: $policy_name.");
            }
        }
        if ($input->getArgument('source1') === null) {
            $source = $io->choice(
                question: "Which source to diff as the original policy?",
                choices: array_keys($policy_sources),
            );
            $source1 = $policy_sources[$source];
            unset($policy_sources[$source]);
        } else {
            $source1 = $this->policyFactory->getSource($input->getArgument('source1'));
            if (isset($policy_sources)) {
                unset($policy_sources[$source1->name]);
            }
        }

        if ($input->getArgument('source2') === null && empty($policy_sources)) {
            throw new LogicException("There are not enough sources to diff this policy.");
        } elseif ($input->getArgument('source2') === null) {
            $source2 = count($policy_sources) == 1 ? array_shift($policy_sources) : $policy_sources[$io->choice(
                question: "Which source to diff as the comparative policy?",
                choices: array_keys($policy_sources),
            )];
        } else {
            $source2 = $this->policyFactory->getSource($input->getArgument('source2'));
        }

        $policy1_list = $source1->getList($this->languageManager);
        $policy2_list = $source2->getList($this->languageManager);

        $policy_name = $input->getArgument('policy');

        if (!isset($policy1_list[$policy_name])) {
            $io->error("Policy '$policy_name' does not exist in source: " . $input->getArgument('source1'));
            return 1;
        }

        if (!isset($policy2_list[$policy_name])) {
            $io->error("Policy '$policy_name' does not exist in source: " . $input->getArgument('source2'));
            return 1;
        }

        $policy1 = $source1->load($policy1_list[$policy_name]);
        $policy2 = $source2->load($policy2_list[$policy_name]);

        $policy1_formatted = $this->preparePolicy($policy1);
        $policy2_formatted = $this->preparePolicy($policy2);

        $builder = new UnifiedDiffOutputBuilder(
            header: '--- ' . $input->getArgument('source1') . " {$policy1->uri}\n+++ " . $input->getArgument('source2') . ' ' . $policy2->uri,
            addLineNumbers: false
        );
        $differ = new Differ($builder);
        $lines = explode(PHP_EOL, $differ->diff($policy1_formatted, $policy2_formatted));
        foreach ($lines as $line) {
            $io->writeln(match (substr($line, 0, 1)) {
                '+' => "<fg=green>$line</>",
                '-' => "<fg=red>$line</>",
                default => $line
            });
        }
        return 0;
    }

    /**
     * @return \Drutiny\PolicySource\AbstractPolicySource[]
     */
    protected function getSourcesByPolicyName(string $policy_name):array
    {
        $policy_sources = [];

        foreach ($this->policyFactory->sources as $source) {
            $list = $this->policyFactory->getSource($source->name)->getList($this->languageManager);

            if (isset($list[$policy_name])) {
                $policy_sources[$source->name] = $this->policyFactory->getSource($source->name);
            }
        }
        return $policy_sources;
    }

    protected function preparePolicy(Policy $policy)
    {
        $export = $policy->export();

        unset($export['source']);
        unset($export['uri']);

        ksort($export);

        return Yaml::dump($export, 6, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
}
