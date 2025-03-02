<?php

namespace Drutiny\Audit\Html;

use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Sandbox\Sandbox;

/**
 * Run a local command and analyse the output.
 */
#[Parameter(
    name: 'url',
    type: Type::STRING,
    mode: Parameter::REQUIRED,
    description: 'Path to local command. Absolute or in user PATH.',
)]
#[Parameter(
    name: 'xpath',
    type: Type::STRING,
    mode: Parameter::REQUIRED,
    description: 'An XPath query to run against the downloaded HTML document.'
)]
class XPathAnalysis extends AbstractAnalysis
{
  /**
   * @inheritdoc
   */
    public function gather(Sandbox $sandbox)
    {
        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = false;
      // Automatically accept minor HTML issues (tags closed improperly, etc.)
        $doc->recover = true;
        @$doc->loadHTML(file_get_contents($this->getParameter('url')));

        $xpath = new \DOMXPath($doc);
        $entries = $xpath->query($this->getParameter('xpath'));

        $html = [];
        $text = [];
        foreach ($entries as $entry) {
            $html[] = $doc->saveXML($entry);
            $text[] = $entry->nodeValue;
        }

        $this->set('html', $html);
        $this->set('text', $text);
    }
}
