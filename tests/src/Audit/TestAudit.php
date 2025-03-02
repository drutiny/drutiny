<?php

namespace DrutinyTests\Audit;

use Drutiny\Attribute\Version;
use Drutiny\Audit\AbstractAnalysis;

#[Version('10.4', '^9.8 || ^10.0')]
class TestAudit extends AbstractAnalysis
{

}
