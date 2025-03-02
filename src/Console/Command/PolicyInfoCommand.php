<?php

namespace Drutiny\Console\Command;

use Drutiny\LanguageManager;
use Drutiny\Policy\Compatibility\PolicyCompatibilityException;
use Drutiny\PolicyFactory;
use Drutiny\Profile;
use Drutiny\ProfileFactory;
use Drutiny\ProfileSource\ProfileCompilationException;
use Drutiny\Report\Format\Markdown;
use Fiasco\SymfonyConsoleStyleMarkdown\Renderer;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment;

/**
 *
 */
class PolicyInfoCommand extends DrutinyBaseCommand
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
        ->setName('policy:info')
        ->setDescription('Show information about a specific policy.')
        ->addArgument(
            'policy',
            InputArgument::REQUIRED,
            'The name of the check to run.'
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

        $policy = $this->policyFactory->loadPolicyByName($input->getArgument('policy'));

        try {
            $policy->isCompatible();
        } catch (PolicyCompatibilityException $e) {
            $io->warning($e->getMessage());
        }

        $template = $this->twigEnvironment->load('docs/policy.md.twig');
        $markdown = $template->render($policy->export());

        $formatted_output = Renderer::createFromMarkdown(Markdown::formatTables($markdown));
        $output->writeln((string) $formatted_output);

        $profiles = array_map(function ($profile) use ($output) {
            try {
                return $this->profileFactory->loadProfileByName($profile['name']);
            } catch (ProfileCompilationException $e) {
                $output->writeln('<warning>' . $e->getMessage() . '</warning>');
            }
        }, $this->profileFactory->getProfileList());

        $profiles = array_filter($profiles);
        
        $io->title('Profiles');
        $profiles = array_filter($profiles, function (Profile $profile) use ($policy) {
            $list = array_keys($profile->policies);
            return in_array($policy->name, $list);
        });
        $io->listing(array_map(function (Profile $profile) {
            return $profile->name;
        }, $profiles));
        return 0;
    }
}
