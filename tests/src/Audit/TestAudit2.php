<?php

namespace DrutinyTests\Audit;

use Drutiny\Attribute\Version;
use Drutiny\Audit\AbstractAnalysis;

#[Version('4.6', '^4.0')]
class TestAudit2 extends AbstractAnalysis
{

}
