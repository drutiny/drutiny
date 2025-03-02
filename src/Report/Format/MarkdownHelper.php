<?php

namespace Drutiny\Report\Format;

use Parsedown;

class MarkdownHelper extends Parsedown
{

  // Remove Code as a block type. This prevents code blocks occuring
  // from indentation as it becomes confusing in YAML template files.
    protected $unmarkedBlockTypes = array('Foo');

  // $unmarkedBlockTypes cannot be empty due to a parsing bug so blockFoo() must
  // be defined.
    protected function blockFoo()
    {
        return;
    }

    protected function blockTable($Line, ?array $Block = null)
    {
        $block = parent::blockTable($Line, $Block);
        if (isset($block['element']['name'])) {
          // Add the class attribute to the <table> tag.
            $block['element']['attributes']['class'] = 'table table-hover';

          // Add the table-active class to the thead > tr element.
            $block['element']['text'][0]['text'][0]['attributes']['class'] = 'table-active';

          // Add the scope='col' attribute to the th elements.
            $thead_columns = $block['element']['text'][0]['text'][0]['text'] ?? [];
            foreach ($thead_columns as $i => $col) {
                $block['element']['text'][0]['text'][0]['text'][$i]['attributes']['scope'] = 'col';
            }
        }

        return $block;
    }
}
