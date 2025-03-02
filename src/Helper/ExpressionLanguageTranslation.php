<?php

namespace Drutiny\Helper;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(autowire: false)]
class ExpressionLanguageTranslation
{
    public function __construct(
        protected string $expression
    ) {
    }

    public function toTwigSyntax():string
    {
        $tokens = [
            'drupal_module_enabled' => 'Drupal.moduleIsEnabled',
            '||' => 'or',
            'AcquiaEnv()' => "target['acquia.cloud.environment']"
        ];

        $regex_tokens = [
            "#[pP]olicy\(['\"]([a-zA-Z0-9:\-_]+)['\"]\) == ['\"]success['\"]#" => "Policy.succeeds('$1')",
            "#target\(['\"]([a-z_\.A-Z0-9\-]+)['\"]\)#" => "target.$1",
            "#semver_gt\(([^,]+), ?['\"]?([0-9\.]+)['\"]?\)#" => "semver_satisfies($1, '^$2')",
            "#array_key_exists\(['\"]([^'\"]+)['\"], ([a-zA-Z0-9\-_]+)\)#" => "($2[$1] is defined)",
            "#\!([^=])#" => 'not $1',
        ];

        $this->expression = preg_replace(array_keys($regex_tokens), array_values($regex_tokens), $this->expression);

        return strtr($this->expression, $tokens);
    }
}
