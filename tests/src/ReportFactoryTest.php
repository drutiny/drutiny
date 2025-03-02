<?php

namespace DrutinyTests;

use DateTime;
use Drutiny\AuditResponse\State;
use Drutiny\Helper\Json;
use Drutiny\Profile;
use Drutiny\ProfileFactory;
use Drutiny\Report\Report;
use Drutiny\Report\ReportFactory;
use Error;
use Exception;

class ReportFactoryTest extends KernelTestCase
{

    public function testCreateReport()
    {
        $profile = $this->getProfile('test');
        $target = $target = $this->loadMockTarget();
        $startDate = new DateTime('-3 days');
        $endDate = new DateTime('-2 days');
        $profile->setReportingPeriod($startDate, $endDate);

        $reportFactory = $this->getReportFactory();
        $report = $reportFactory->create(
            profile: $profile,
            target: $target,
        );

        $this->assertInstanceOf(Report::class, $report, "ReportFactory produced a report.");
        $this->assertEquals($startDate, $report->reportingPeriodStart, "Report uses the correct start date.");
        $this->assertEquals($endDate, $report->reportingPeriodEnd, 'Report using the correct reporting end date.');
        $this->assertEquals(count($profile->policies), count($report->results), "Number of results equals number of policies.");
        $this->assertFalse($report->successful);
        $this->assertEquals($report->results['Test:Fail']->policy->severity, $report->severity, "Severity of Test:Fail is set as report severity.");
        $this->assertEquals(State::SUCCESS, $report->results['Test:Pass']->state, "Test:Pass policy passed");
        $this->assertEquals(State::FAILURE, $report->results['Test:Fail']->state, "Test:Fail policy failed.");
        $this->assertEquals(State::WARNING, $report->results['Test:Warning']->state, "Test:Warning policy warned.");
        $this->assertEquals(State::ERROR, $report->results['Test:Error']->state, "Test:Error policy errored.");
        $this->assertEquals(State::NOTICE, $report->results['Test:Notice']->state, "Test:Notice policy issued a notice.");
        $this->assertEquals(State::NOT_APPLICABLE, $report->results['Test:NA']->state, "Test:NA policy was not applicable.");
        // echo json_encode(Json::extract($report), JSON_PRETTY_PRINT);die;
    }

    public function testBadReportCreation()
    {
        /**
         * @var \Drutiny\Profile
         */
        $profile = $this->container->get(ProfileFactory::class)->loadProfileByName('test');
        $target = $target = $this->loadMockTarget();

        $this->expectException(Error::class);
        $report = new Report(
            uri: 'https://phpunit.test',
            profile: $profile,
            target: $target
        );
    }

    protected function getProfile($name):Profile
    {
        return $this->container->get(ProfileFactory::class)->loadProfileByName($name);
    }

    protected function getReportFactory():ReportFactory
    {
        return $this->container->get(ReportFactory::class);
    }
}
