<?php

namespace Drutiny\Console\Command;

use Drutiny\Attribute\Autoload;
use Drutiny\LanguageManager;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;

/**
 *
 */
trait LanguageCommandTrait
{
    #[Autoload(early: true)]
    protected LanguageManager $languageManager;
    
    /**
     * @inheritdoc
     */
    protected function configureLanguage()
    {
        $this
        ->addOption(
            'language',
            '',
            InputOption::VALUE_OPTIONAL,
            'Define which language to use for policies and profiles.',
            $this->languageManager->getDefaultLanguage()
        );
    }

    protected function initLanguage(InputInterface $input)
    {
      // Set global language used by policy/profile sources.
        $this->languageManager->setLanguage($input->getOption('language'));
    }
}
