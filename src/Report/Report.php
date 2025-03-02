<?php

namespace Drutiny\Report;

use DateTimeInterface;
use DateTimeZone;
use Drutiny\Attribute\ArrayType;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Console\ProcessManager;
use Drutiny\Helper\MergeUtility;
use Drutiny\Policy\Severity;
use Drutiny\Profile;
use Drutiny\Sandbox\ReportingPeriodTrait;
use Drutiny\Target\TargetInterface;
use Exception;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Process\Process;

#[Autoconfigure(autowire: false)]
class Report
{
    use ReportingPeriodTrait;

    public readonly string $uuid;
    
    /**
     * @var AuditResponse[]
     */
    #[ArrayType('keyed', AuditResponse::class)]
    public readonly array $results;
    public readonly Severity $severity;
    public readonly bool $successful;

    public function __construct(
        public readonly string $uri,
        public readonly Profile $profile,
        public readonly TargetInterface $target,
        public readonly ReportType $type = ReportType::ASSESSMENT,
        array $results = [],
        public readonly ?int $timing = null,
        public readonly string $language = 'und',
        public readonly DateTimeInterface $created = new \DateTime(timezone: new DateTimeZone('UTC')),
    ) {
        $this->setReportingPeriod($profile->reportingPeriodStart, $profile->reportingPeriodEnd);

        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $this->uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        // Ensure each item in the array is an AuditResponse and make the results
        // array a keyed array of results.
        $this->results = array_combine(
            array_map(fn(AuditResponse $r) => $r->policy->name, $results),
            array_map(fn(AuditResponse $r) => $r, $results)
        );

        if (empty($results)) {
            $this->severity = Severity::getDefault();
            $this->successful = true;
            return;
        }

        // Severity is the high (heaviest) severity weight of an unsuccessful policy.
        $this->severity = array_reduce($this->results, function (Severity $c, AuditResponse $i) {
            return $i->state->isSuccessful() ? $c : Severity::fromInt(max($c->getWeight(), $i->policy->severity->getWeight()));
        }, Severity::getDefault());

        // Successful means all results were successful.
        $this->successful = array_reduce($this->results, function (AuditResponse|null $a, AuditResponse $b) {
            $a ??= $b;
            return !$a->state->isSuccessful() ? $a : $b;
        })->state->isSuccessful();
    }

    public function resolve(ProcessManager $processManager): self
    {
        if (count($this->results)) {
            throw new RuntimeException("Report already has results. Cannot resolve results from processManager.");
        }

        while (!$processManager->hasFinished()) {
            $processManager->update();
            if (!$processManager->hasFinished()) {
                sleep(1);
            }
        }

        /**
         * @var Array[Drutiny\AuditResponse\AuditResponse[]]
         */
        $results = $processManager->map(function (Process $process):array {
            return unserialize(base64_decode($process->getOutput()));
        });

        return $this->with(results: array_merge(...$results));
    }

    /**
     * Produce report object variation with altered properties.
     */
    public function with(...$properties):self
    {
        $args = MergeUtility::arrayMerge(get_object_vars($this), $properties);

        $keys = ['uri', 'profile', 'target', 'type', 'results', 'timing', 'language'];
        foreach ($args as $key => $arg) {
            if (!in_array($key, $keys)) {
                unset($args[$key]);
            }
        }
        return new static(...$args);
    }

    /**
     * Get an identifiable name of the report.
     *
     * Although this name contains the date, for a completely
     * unique name, use or incorporate the UUID.
     */
    public function getName(): string
    {
        return strtr(implode('-', [
            $this->target->getTargetName(),
            $this->profile->name,
            $this->uri,
            $this->reportingPeriodStart->format('Ymd-His'),
          ]) . '.' . $this->language, [
            ':' => '',
            '@' => '',
            '/' => '',
            '?' => '',
            '#' => '',
            '&' => '',
          ]);
    }
}
