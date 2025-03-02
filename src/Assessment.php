<?php

namespace Drutiny;

use Drutiny\AuditResponse\AuditResponse;
use Drutiny\AuditResponse\NoAuditResponseFoundException;
use Drutiny\Entity\ExportableInterface;
use Drutiny\Entity\SerializableExportableTrait;
use Drutiny\Report\Report;
use Drutiny\Sandbox\ReportingPeriodTrait;
use Drutiny\Target\TargetInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Legacy Assessment wrapper for Report.
 */
#[Autoconfigure(autowire:false)]
class Assessment implements ExportableInterface, AssessmentInterface, \Serializable
{
    use ReportingPeriodTrait;
    use SerializableExportableTrait {
        import as importUnserialized;
    }

    protected array $statsByResult = [];
    protected array $statsBySeverity = [];

    public function __construct(
        public readonly Report $report
    ) {
        $this->setReportingPeriod($report->reportingPeriodStart, $report->reportingPeriodEnd);
        array_map(fn($r) => $this->captureStats($r), $report->results);
    }

    public function getUuid(): string
    {
        return $this->report->uuid;
    }

    public function getType(): string
    {
        return $this->report->type->value;
    }

    /**
     * Capture statistics about the response in the context of the assessment.
     */
    protected function captureStats(AuditResponse $response):void
    {
        $this->statsByResult[$response->getType()] = $this->statsByResult[$response->getType()] ?? 0;
        $this->statsByResult[$response->getType()]++;

        $this->statsBySeverity[$response->getSeverity()][$response->getType()] = $this->statsBySeverity[$response->getSeverity()][$response->getType()] ?? 0;
        $this->statsBySeverity[$response->getSeverity()][$response->getType()]++;
    }

    /**
     *  Get severity code.
     *
     * @return int [description]
     */
    public function getSeverityCode(): int
    {
        return $this->report->severity->getWeight();
    }

    /**
     * Get the overall outcome of the assessment.
     */
    public function isSuccessful():bool
    {
        return $this->report->successful;
    }

    /**
     * Check if an AuditResponse exists by name.
     *
     * @param string $name
     * @return bool
     */
    public function hasPolicyResult(string $name):bool
    {
        return isset($this->report->results[$name]);
    }

    /**
     * Get an AuditResponse object by Policy name.
     *
     * @param string $name
     * @return AuditResponse
     */
    public function getPolicyResult(string $name):AuditResponse
    {
        if (!$this->hasPolicyResult($name)) {
            throw new NoAuditResponseFoundException($name, "Policy '$name' does not have an AuditResponse. Found " . implode(', ', array_keys($this->report->results)));
        }
        return $this->report->results[$name];
    }

    /**
     * Get the results array of AuditResponse objects.
     *
     * @return array of AuditResponse objects.
     */
    public function getResults():array
    {
        return $this->report->results;
    }

    /**
     * @deprecated use getSeverityCode()
     */
    public function getErrorCode():int|false
    {
        return $this->report->successful ? false : $this->report->severity->getWeight();
    }

    /**
     * Get the uri of Assessment object.
     *
     * @return string uri.
     */
    public function uri():string
    {
        return $this->report->uri;
    }

    public function getStatsByResult()
    {
        return $this->statsByResult;
    }

    public function getStatsBySeverity()
    {
        return $this->statsBySeverity;
    }

    public function getTarget(): TargetInterface
    {
        return $this->report->target;
    }

    /**
     * {@inheritdoc}
     */
    public function export():array
    {
        return [
        'uri' => $this->report->uri,
        'uuid' => $this->report->uuid,
        'results' => $this->report->results,
        'successful' => $this->report->successful,
        'errorCode' => $this->getSeverityCode(),
        'targetReference' => $this->report->target->getTargetName(),
        'report' => serialize($this->report)
        ];
    }

    public function import(array $export)
    {
        $this->report = unserialize($export['report']);
    }

    public function getRequestTime(): int
    {
        return REQUEST_TIME;
    }
}
