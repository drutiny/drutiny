<?php

namespace Drutiny\AuditResponse;

use Drutiny\CheckInformation;

/**
 * Class AuditResponse.
 *
 * @package Drutiny\AuditResponse
 */
class AuditResponse {

  const NA = -1;
  const SUCCESS = 0;
  const WARNING = 1;
  const FAILURE = 2;
  const ERROR = 3;
  const REMEDIATED = 4;

  protected $info;

  protected $state = AuditResponse::NA;

  protected $tokens = [];

  /**
   * AuditResponse constructor.
   *
   * @param mixed $state
   *   A bool|int|null indicating the outcome of a Drutiny\Check\Check.
   */
  public function __construct(CheckInformation $info) {
    $this->info = $info;
  }

  /**
   * Set the state of the response.
   */
  public function set($state = NULL, array $tokens) {
    if ($state === TRUE) {
      $state = self::SUCCESS;
    }
    elseif ($state === FALSE) {
      $state = self::FAILURE;
    }
    elseif (is_null($state)) {
      $state = self::NA;
    }
    elseif (!is_int($state) || $state > self::REMEDIATED) {
      $state = self::SUCCESS;
    }
    $this->state = $state;
    $this->tokens = $tokens;
  }

  /**
   * Get the title.
   *
   * @return string
   *   The checks title.
   */
  public function getTitle() {
    return $this->info->get('title', $this->tokens);
  }

  /**
   * Get the description for the check performed.
   *
   * @return string
   *   Translated description.
   */
  public function getDescription() {
    return $this->info->get('description', $this->tokens);
  }

  /**
   * Get the remediation for the check performed.
   *
   * @return string
   *   Translated description.
   */
  public function getRemediation() {
    return $this->info->get('remediation', $this->tokens);
  }

  /**
   * Get the failure message for the check performed.
   *
   * @return string
   *   Translated description.
   */
  public function getfailure() {
    return $this->info->get('failure', $this->tokens);
  }

  /**
   * Get the success message for the check performed.
   *
   * @return string
   *   Translated description.
   */
  public function getSuccess() {
    return $this->info->get('success', $this->tokens);
  }

  /**
   *
   */
  public function isSuccessful() {
    return $this->state === AuditResponse::SUCCESS || $this->state == AuditResponse::REMEDIATED;
  }

  /**
   * Get the response based on the state outcome.
   *
   * @return string
   *   Translated description.
   */
  public function getSummary() {
    $response = [
      'summary' => '',
      'type' => 'info',
    ];
    switch ($this->state) {
      case AuditResponse::ERROR:
      case AuditResponse::NA:
        return strtr('Could not determine the state of @title due to an error:
```
@exception
```', $this->tokens);

      break;

      case AuditResponse::SUCCESS:
      case AuditResponse::REMEDIATED:
        return $this->info->get('success', $this->tokens);

      break;

      case AuditResponse::FAILURE:
      case AuditResponse::WARNING:
        $summary = $this->info->get('failure', $this->tokens);
        if ($this->info->get('remediable')) {
          $summary .= PHP_EOL . $this->info->get('remediation', $this->tokens);
        }
        return $summary;

      break;

      default:
        throw new \InvalidArgumentException("Unknown AuditResponse state. Cannot generate summary.");
    }
  }

}
