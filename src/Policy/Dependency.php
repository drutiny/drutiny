<?php

namespace Drutiny\Policy;

use Attribute;
use Drutiny\Helper\ExpressionLanguageTranslation;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(autowire: false)]
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
class Dependency
{

    /**
     * On fail behaviour: Fail policy in report.
     */
      const ON_FAIL_DEFAULT = 'fail';

    /**
     * On fail behaviour: Omit policy from report.
     */
      const ON_FAIL_OMIT = 'omit';

    /**
     * On fail behaviour: Report policy as error.
     */
      const ON_FAIL_ERROR = 'error';

    /**
     * On fail behaviour: Report as not applicable.
     */
      const ON_FAIL_REPORT_ONLY = 'report_only';

    public readonly DependencyBehaviour $onFail;
    public readonly string $expression;
    public readonly string $syntax;

    public function __construct(
        string $expression = 'true',
        string $on_fail = self::ON_FAIL_DEFAULT,
        string $syntax = 'expression_language',
        public readonly string $description = ''
    ) {
        $this->onFail = DependencyBehaviour::get($on_fail);
        
        // Gracefully port expression language syntax into twig.
        if ($syntax == 'expression_language') {
            $translation = new ExpressionLanguageTranslation($expression);
            $expression = $translation->toTwigSyntax();
            $syntax = 'twig';
        }
        $this->syntax = $syntax;
        $this->expression = $expression;
    }

    public function export()
    {
        return [
            'on_fail' => $this->onFail->label(),
            'expression' => $this->expression,
            'syntax' => $this->syntax
        ];
    }

    /**
     * @deprecated use description property.
     */
    public function getDescription():string
    {
        return $this->description;
    }

    /**
     * When dependencies are expressed as a string, they implicitly refer to another policy.
     */
    public static function fromString(string $dependency):Dependency
    {
        return new static(
            syntax: 'twig',
            expression: sprintf('Policy.succeeds("%s")', $dependency),
            description: "Ensure policy '$dependency' passes.",
        );
    }
}
