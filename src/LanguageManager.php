<?php

namespace Drutiny;

class LanguageManager
{
    protected string $defaultLanguageCode;
    protected string $languageCode;

    public function __construct(Settings $settings)
    {
        $this->defaultLanguageCode = $settings->get('language_default');
    }

    public function getCurrentLanguage():string
    {
        return $this->languageCode ?? $this->defaultLanguageCode;
    }

    public function getDefaultLanguage():string
    {
        return $this->defaultLanguageCode;
    }

    public function setLanguage($lang_code = 'en'):void
    {
        $this->languageCode = $lang_code;
    }
}
