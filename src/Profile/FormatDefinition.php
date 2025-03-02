<?php

namespace Drutiny\Profile;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Twig\TemplateWrapper;

#[Autoconfigure(autowire:false)]
class FormatDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $template = '',
        public readonly string|TemplateWrapper $content = '',
    ) {
    }

    /**
     * Produce format definition object variation with altered properties.
     */
    public function with(...$properties):self
    {
        $args = array_merge(get_object_vars($this), $properties);
        return new static(...$args);
    }
}
