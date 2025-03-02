<?php

namespace Drutiny\Sandbox;

use DateInterval;
use DateTime;
use DateTimeInterface;
use DateTimeZone;

/**
 *
 */
trait ReportingPeriodTrait
{

    /**
     * @var DateTime The begining of the reporting period.
     */
    public readonly DateTimeInterface $reportingPeriodStart;

    /**
     * @var DateTime The end of the reporting period.
     */
    public readonly DateTimeInterface $reportingPeriodEnd;

    public function setReportingPeriod(DateTimeInterface $start, DateTimeInterface $end):self
    {
        if (isset($this->logger)) {
            $this->logger->debug(strtr("Reporting period set @start to @end", [
            '@start' => $start->format('Y-m-d H:i:s e'),
            '@end' => $end->format('Y-m-d H:i:s e'),
            ]));
        }
        return $this->setReportingPeriodStart($start)
                  ->setReportingPeriodEnd($end);
    }

    public function setReportingPeriodStart(DateTimeInterface $start):self
    {
        $this->reportingPeriodStart = $start;
        return $this;
    }

    public function setReportingPeriodEnd(DateTimeInterface $end):self
    {
        $this->reportingPeriodEnd = $end;
        return $this;
    }

    /**
     * @deprecated use reportingPeriodStart property
     */
    public function getReportingPeriodStart():DateTimeInterface
    {
        if (empty($this->reportingPeriodStart)) {
            $this->reportingPeriodStart = new DateTime();
        }
        return $this->reportingPeriodStart;
    }

    /**
     * @deprecated use reportingPeriodEnd property.
     */
    public function getReportingPeriodEnd():DateTimeInterface
    {
        if (empty($this->reportingPeriodEnd)) {
            $this->reportingPeriodEnd = new DateTime();
        }
        return $this->reportingPeriodEnd;
    }

    /**
     * @return DateInterval
     */
    public function getReportingPeriodInterval():DateInterval
    {
        return $this->reportingPeriodEnd->diff($this->reportingPeriodStart);
    }

    public function getReportingPeriodDuration():int
    {
        $interval = $this->getReportingPeriodInterval();
               // seconds
        $seconds = $interval->s
               // minutes to seconds
               + ($interval->i * 60)
               // hours to seconds
               + ($interval->h * 3600)
               // days to seconds
               + ($interval->d * 86400)
               // months to seconds
               + ($interval->m * 2592000)
               // years to seconds
               + ($interval->y * 31536000);
        return $seconds;
    }

    /**
     * Get sensible intervals to use for duration grainularity.
     */
    protected function _getReportingPeriodIntervals():array
    {
        return [
        30, // 30 seconds
        60, // 1 minute
        120, // 2 minutes
        180, // 3 minutes
        300, // 5 minutes
        600, // 10 minutes
        900, // 15 minutes
        1800, // 30 minutes
        3600, // 1 hour
        7200, // 2 hours
        10800, // 3 hours
        14400, // 4 hours
        21600, // 6 hours
        28800, // 8 hours
        43200, // 12 hours
        86400, // 1 day
        172800, // 2 days
        259200, // 3 days
        432000, // 5 days
        604800, // 7 days
        ];
    }

    /**
     * Get the step interval in seconds.
     *
     * @return int Number of seconds in each step.
     */
    public function getReportingPeriodSteps():int
    {
        $duration = $this->getReportingPeriodDuration();

        $steps = array_map(function ($interval) use ($duration) {
            return (int) ceil($duration / $interval);
        }, $this->_getReportingPeriodIntervals());

        $steps = array_combine($this->_getReportingPeriodIntervals(), $steps);

        $steps = array_filter($steps, function ($step) {
          // Filter intervals to those resulting in 51 to 100 steps.
            return $step > 50 && $step <= 100;
        });

        if (empty($steps)) {
          // If the duration is less than 30mins, then set the stepping period
          // to 30 seconds.
            if ($duration < 1800) {
                return 30;
            }
        }

      // Return the key from the first element in the steps array, which should be the largest number of steps.
        return key($steps);
    }
}
