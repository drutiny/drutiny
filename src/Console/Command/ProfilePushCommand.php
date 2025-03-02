<?php

namespace Drutiny\Console\Command;

use Drutiny\Attribute\AsSource;
use Drutiny\LanguageManager;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Drutiny\ProfileFactory;
use Drutiny\ProfileSource\PushableProfileSourceInterface;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Exception\InvalidArgumentException;

#[AsCommand(name: 'profile:push', description: 'Push a profile to a profile source.')]
class ProfilePushCommand extends DrutinyBaseCommand
{
    use LanguageCommandTrait;

    public function __construct(
        protected ProfileFactory $profileFactory,
        protected LoggerInterface $logger,
        protected LanguageManager $languageManager,
    ) {
        parent::__construct();
    }
  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->addArgument(
            'profile',
            InputArgument::REQUIRED,
            'The name of the profile to push.'
        )
        ->addArgument(
            'source',
            InputArgument::OPTIONAL,
            'The name of the source to push too.'
        )
        ->addOption(
            'commit-msg',
            'm',
            InputOption::VALUE_OPTIONAL,
            'A message detailing the changes involved in the push.',
            ''
        )
        ->addOption(
            'from',
            'f',
            InputOption::VALUE_OPTIONAL,
            'The name of the source to load the profile from.'
        );
        $this->configureLanguage();
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLanguage($input);
        $remote = $this->getPushSource($input, $output);

        $io = new SymfonyStyle($input, $output);

        if (!($remote instanceof PushableProfileSourceInterface)) {
            $io->error('Source does not support policy push');
            return 1;
        }

        if ($source = $input->getOption('from')) {
            $source = $this->profileFactory->getSource($source);
        }

        $profile = $this->profileFactory->loadProfileByName($input->getArgument('profile'), $source);

        try {
            $profile = $remote->push($profile, $input->getOption('commit-msg'));
        } catch (IdentityProviderException $e) {
            $this->logger->error(get_class($e));
            $this->logger->error($e->getMessage());
            return 2;
        }

        $io->success(sprintf(
            'Profile %s successfully pushed to %s. Visit %s',
            $input->getArgument('profile'),
            $input->getArgument('source'),
            $profile->uri
        ));

        return 0;
    }

    /**
     * Find an appropriate source to push too.
     */
    protected function getPushSource(InputInterface $input, OutputInterface $output):PushableProfileSourceInterface
    {
        $io = new SymfonyStyle($input, $output);
        if ($source_name = $input->getArgument('source')) {
            return $this->profileFactory->getSource($input->getArgument('source'));
        }
        $sources = array_filter($this->profileFactory->sources, function (AsSource $source) {
            return $this->profileFactory->getSource($source->name) instanceof PushableProfileSourceInterface;
        });
        if (count($sources) == 1) {
            $name = array_shift($sources)->name;
            if ($io->confirm("Push profile to source '$name'?")) {
                return $this->profileFactory->getSource($name);
            }
            throw new InvalidArgumentException("There are no pushable sources to push profiles too.");
        }
        if (count($sources) == 0) {
            throw new InvalidArgumentException("There are no pushable sources to push profiles too.");
        }
        $choice = $io->choice("Which source would you like to push to?", array_keys($sources));
        return $this->profileFactory->getSource($sources[$choice]);
    }
}
