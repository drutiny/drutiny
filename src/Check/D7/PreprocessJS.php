<?php

namespace Drutiny\Check\D7;

use Drutiny\Check\Check;

/**
 * @Drutiny\Annotation\CheckInfo(
 *  title = "JS aggregation",
 *  description = "With JS optimization disabled, your website visitors are experiencing slower page performance and the server load is increased.",
 *  remediation = "Set the variable <code>preprocess_js</code> to be <code>1</code>.",
 *  success = "JS aggregation is enabled.:fixups",
 *  failure = "JS aggregation is not enabled.",
 *  exception = "Could not determine JS aggregation setting.",
 *  supports_remediation = TRUE,
 * )
 */
class PreprocessJS extends Check {

  /**
   * @inheritDoc
   */
  public function check() {
    return (bool) (int) $this->context->drush->getVariable('preprocess_js', 0);
  }

  /**
   * @inheritDoc
   */
  public function remediate() {
    $res = $this->context->drush->setVariable('preprocess_js', 1);
    return $res->isSuccessful();
  }

}
