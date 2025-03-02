<?php

namespace Drutiny\Report\Format;

use Drutiny\Report\FormatInterface;
use Drutiny\Attribute\AsFormat;
use Drutiny\Profile\FormatDefinition;

#[AsFormat(
    name: 'html',
    extension: 'html'
)]
class HTML extends TwigFormat
{
    public function setDefinition(FormatDefinition $definition): FormatInterface
    {
        if (empty($definition->content)) {
            $definition = $definition->with(content: $this->loadTwigTemplate('report/profile'));
        } elseif (is_string($definition->content)) {
            $definition = $definition->with(content: $this->twig->createTemplate($definition->content, $this->getName()));
        }
        if (empty($definition->template)) {
            $definition = $definition->with(template: 'report/page.' . $this->getExtension() . '.twig');
        }
        return parent::setDefinition($definition);
    }

    protected function prepareContent(array $variables):array
    {
        $sections = [];

      // In 3.x we support Twig TemplateWrappers to be passed directly
      // to the report format.

        foreach ($this->definition->content->getBlockNames() as $block) {
            $sections[$block] = $this->definition->content->renderBlock($block, $variables);
        }
        return $sections;
    }
}
