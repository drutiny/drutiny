<?php

namespace Drutiny;

use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Target\TargetInterface;

interface AssessmentInterface
{
    public function getErrorCode();
    public function getPolicyResult(string $name):AuditResponse;
    public function getResults();
    public function getSeverityCode(): int;
    public function getStatsByResult();
    public function getStatsBySeverity();
    public function getTarget(): TargetInterface;
    public function getType(): string;
    public function getUuid(): string;
    public function hasPolicyResult(string $name):bool;
    public function isSuccessful();
    public function uri();
}
