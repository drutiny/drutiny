<?php

namespace Drutiny\Report\Twig;

use Drutiny\Assessment;
use Drutiny\Audit\TwigEvaluator;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Policy\Chart;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Error\RuntimeError;

class Helper {
  /**
   * Registered as a Twig filter to be used as: "Title here"|heading.
   */
  public static function filterSectionHeading(Environment $env, $heading, $level = 2)
  {
    return $env
      ->createTemplate('<h'.$level.' class="section-title" id="section_{{ heading | u.snake }}">{{ heading }}</h'.$level.'>')
      ->render(['heading' => $heading]);
  }

  /**
   * Extract a value out of a string using a regex.
   */
  public static function filterExtract(string $text, string $regex, int $match = 0): string {
    preg_match($regex, $text, $matches);
    return $matches[$match] ?? '';
  }

  /**
   * Registered as a Twig filter to be used as: chart.foo|chart.
   */
  public static function filterChart(Chart|null|array $chart, string $data_table = ''):string
  {
    if (is_null($chart)) {
      return '';
    }
    if (is_array($chart)) {
      $chart = Chart::fromArray($chart, $chart['id'] ?? 'chart' . mt_rand(1000, 9999));
    }
    if (!empty($data_table)) {
      $chart = $chart->with(tableIndex: -1);
    }
    $class = 'chart-unprocessed placeholder-glow';
    if (isset($chart->htmlClass)) {
        $class .= ' '.$chart->htmlClass;
    }
    $element = '<div id="' . $chart->id . '" class="'.$class.'" ';
    foreach (get_object_vars($chart) as $name => $key) {
      $name = strtolower(preg_replace('/[A-Z]/', '-$0', $name));
      $value = is_array($key) ? implode(',', $key) : $key;
      $element .= 'data-chart-'.$name . '='.json_encode($value, JSON_UNESCAPED_UNICODE).' ' ;
    }
    return $element . "><canvas class=\"placeholder\"></canvas>\n\n$data_table\n</div>";
  }

  /**
   * Render a table and chart in a twig templated message.
   * 
   * Each column in the table is a new series of data.
   * The first column is the x-axis.
   * Each row in the $rows array can be an associative array where the
   * keys are the $headers values.
   */
  public static function filterChartTable(array $headers, array $rows, Chart|array $chart, string $pad = '') {
    if (is_array($chart)) {
      $chart = Chart::fromArray($chart, $chart['id'] ?? 'chart' . mt_rand(1000, 9999));
    }

    $chart = $chart->addXaxisLabels('tr td:nth-child(1)');

    for ($i=2; $i < (count($headers) + 1); $i++) {
      $chart = $chart->addSeriesLabel("tr th:nth-child($i)")->addSeries("tr td:nth-child($i)");
    }

    $header_keys = array_is_list($headers) ? $headers : array_keys($headers);

    $element = [implode(' | ', $headers)];
    $element[] = implode(' | ', array_map(fn($h) => str_pad('', strlen($h), '-'), $headers));
    foreach ($rows as $row) {
      if (!array_is_list($row)) {
        // If rows are keyed by header value, then we should pad any absent key.
        foreach ($header_keys as $header) {
          $row[$header] ??= $pad;
        }
        // Ensure the row order reflects the header order.
        $row = array_map(fn($h) => $row[$h], $header_keys);
      }

      $element[] = implode(' | ', $row);
    }
    return self::filterChart($chart, implode(PHP_EOL, $element)) . "\n\n";
  }

  /**
   * Twig policy_result function.
   */
  public static function renderAuditReponse(Environment $twig, AuditResponse $response, Assessment $assessment, ?string $style = null):string
  {
      // Irrelevant responses should be omitted from rendering.
      if ($response->state->isIrrelevant()) {
        return '';
      }
      $globals = $twig->getGlobals();

      $ext = $globals['ext'];
      $type = $response->getType();
      $profile = $assessment->report->profile->name;
      $path = 'report/policy';

      $templates = [
        // Templates using style.
        "$path/$profile-$type-$style.$ext.twig",
        "$path/$type-$style.$ext.twig",
        "$profile/$type-$style.$ext.twig",
        "$type-$style.$ext.twig",

        // Templates without style
        "$path/$profile-$type.$ext.twig",
        "$profile/$type.$ext.twig",
        "$profile/policy.$ext.twig",
        "$type.$ext.twig",
        "policy.$ext.twig",

        // Generic
        "$profile/$type.twig",
        "$profile/policy.twig",
        "$type.twig",
        "policy.twig",

        // Default
        "$path/$type.$ext.twig",
      ];

      $template = $twig->resolveTemplate($templates);
      $globals['logger']->info("Rendering audit response for ".$response->policy->name.' with '.$template->getTemplateName());
      $globals['logger']->debug('Keys: ' . implode(', ', array_keys($response->tokens)));

      /**
       * @var \Drutiny\Audit\TwigEvaluator
       */
      $twigEvaluator = $twig->getRuntime(TwigEvaluator::class);

      $contexts = $twigEvaluator->getGlobalContexts();
      $contexts['audit_response'] = $response;
      $contexts['assessment'] = $assessment;
      $contexts['target'] = $assessment->getTarget();

      try {
        return $twig->render($template, $contexts);
      }
      catch (RuntimeError $e) {
        $message = sprintf("%s of %s:\n%s",$e->getMessage(), $e->getSourceContext()->getName(), $e->getSourceContext()->getCode());
        $twig->getRuntime(LoggerInterface::class)->error($message);
        return '';
      }   
  }

  /**
   * Render a file into base64.
   */
  public static function base64File(Environment $twig, string $path) {
    $template = $twig->resolveTemplate($path);
    return base64_encode($twig->render($template));
  }

  public static function keyed($variable) {
    return is_array($variable) && is_string(key($variable));
  }

  public static function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    // Uncomment one of the following alternatives
      $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
  }

  public static function escapeMarkdown(string $text) {
    return str_replace([
      '\\', '-', '#', '*', '+', '`', '.', '[', ']', '(', ')', '!', '&', '<', '>', '_', '{', '}', '|', ], [
      '\\\\', '\-', '\#', '\*', '\+', '\`', '\.', '\[', '\]', '\(', '\)', '\!', '\&', '\<', '\>', '\_', '\{', '\}', '\|'
    ], $text);
  }

  /**
   * Convert an invalid version into a semantic version.
   */
  public static function semver(string $version, string $behaviour = 'pop'):string {
    // Example of bad version: 7.x-3.10
    $parts = explode('.', $version);

    // Example: 2.x-dev
    if (count($parts) < 3) {
      return $version;
    }

    list($major, $minor, $patch) = $parts;

    $modified_minor = preg_replace('/([^0-9])/', '', $minor);
    
    if ($modified_minor == $minor) {
      return $version;
    }

    return match ($behaviour) {
      'shift' => $major,
      'join' => implode('.', [$major, $modified_minor, $patch]),
      // pop
      default => implode('.', [$modified_minor, $patch])
    };
  }
  
  /**
   * Determine if to use a singular or pluralized term.
   */
  public static function pluralize(array|int $things, $singular, $plural):string {
    if (is_array($things)) {
      $things = count($things);
    }
    return $things > 1 ? $plural : $singular;
  }

  public static function bootstrapColorMap(string $state):string {
    return match (strtolower($state)) {
      'failure' => 'danger',
      'error' => 'danger',
      'irrelevent' => 'secondary',
      'not_applicable' => 'secondary',
      'notice' => 'info',
      'normal' => 'info',
      'critical' => 'danger',
      'high' => 'warning',
      'low' => 'secondary',
      'none' => 'secondary',
      default => strtolower($state),
    };
  }

}

 ?>
