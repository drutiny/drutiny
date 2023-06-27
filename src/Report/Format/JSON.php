<?php

namespace Drutiny\Report\Format;

use DateTime;
use Drutiny\Attribute\AsFormat;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Helper\Json as HelperJson;
use Drutiny\Report\FilesystemFormatInterface;
use Drutiny\Report\FormatInterface;
use Drutiny\Report\Report;
use League\CommonMark\ConverterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Twig\Environment;
use Twig\Extension\CoreExtension;

#[AsFormat(
  name: 'json',
  extension: 'json'
)]
class JSON extends FilesystemFormat implements FilesystemFormatInterface
{
    protected string $name = 'json';
    protected string $extension = 'json';
    protected $data;

    public function __construct(
      protected Environment $twig,
      protected ConverterInterface $converter,
      OutputInterface $output, 
      LoggerInterface $logger)
    {
      parent::__construct($output, $logger);
    }

    protected function prepareContent(Report $report):array
    {
        $this->data = HelperJson::extract($report);

        // Backwards compatibility formats
        $datetime = new DateTime();
        $this->data['date'] = $datetime->format('Y-m-d');
        $this->data['human_date'] = $datetime->format('F jS, Y');
        $this->data['time'] = $datetime->format('h:ia');

        $this->data['reporting_period_start'] = $report->reportingPeriodStart->format('Y-m-d H:i:s e');
        $this->data['reporting_period_end'] = $report->reportingPeriodEnd->format('Y-m-d H:i:s e');

        foreach ($report->results as $name => $response) {
          $this->data['results'][$name]['policy']['rendered'] = $this->preRenderPolicy($response);
          $this->data['policy'][] = $this->data['results'][$name]['policy'];
          $total = $this->data['totals'][$response->getType()] ?? 0;
          $this->data['totals'][$response->getType()] = $total+1;
        }

        $this->data['total'] = array_sum($this->data['totals']);
        return $this->data;
    }

    public function render(Report $report):FormatInterface
    {
        $this->twig->getExtension(CoreExtension::class)->setTimezone($report->reportingPeriodStart->getTimezone());
        $this->buffer->write(json_encode($this->prepareContent($report)));
        return $this;
    }

    protected function preRenderPolicy(AuditResponse $response): array {

      $keys = ['title', 'description', 'success', 'warning', 'failure', 'remediation', 'notes'];
      $values = [
        'name' => $response->policy->name
      ];

      foreach ($keys as $key) {
        if (!property_exists($response->policy, $key)) {
          $values[$key] = null;
          continue;
        }
        $values[$key] = $this->converter->convert(
          $this->twig->render(
            name: $this->twig->createTemplate($response->policy->{$key}),
            context: $response->tokens
          )
        )->getContent();
      }

      return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function write():iterable
    {
      $filepath = $this->directory . '/' . $this->namespace . '.' .  $this->getExtension();
      $stream = new StreamOutput(fopen($filepath, 'w'));
      $stream->write($this->buffer->fetch());
      $this->logger->info("Written $filepath.");
      yield $filepath;
    }
}
