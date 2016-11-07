<?php

namespace SiteAudit\Check\Drush;

use SiteAudit\Check\Check;
use SiteAudit\Annotation\CheckInfo;

/**
 * @CheckInfo(
 *  title = "Page compression",
 *  description = "Drupal's Compress cached pages option (page_compression) can cause unexpected behavior when an external cache such as Varnish is employed, and typically provides no benefit. Therefore, Compress cached pages should be disabled.",
 *  remediation = "Set the variable <code>page_compression</code> to <code>0</code>.",
 *  success = "Compress cached pages is disabled.",
 *  failure = "Compress cached pages (page_compression) is enabled.",
 *  exception = "Could not determine status of page_compression: :exception."
 * )
 */
class PageCompression extends Check {
  public function check()
  {
    $page_compression = (bool) $this->context->drush->getVariable('page_compression', TRUE);
    $this->setToken('page_compression', $page_compression);
    if ($page_compression) {
      return FALSE;
    }
    return TRUE;
  }
}
