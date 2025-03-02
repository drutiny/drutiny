<?php

namespace Drutiny\Attribute;

use Attribute;

/**
 * Use on classes extended from Drutiny\Audit\AbstractAnalysis.
 *
 * This attribute informs the AbstractAnalysis class to gather data
 * from specific methods.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class DataProvider
{
    public function __construct(
        /**
         * Set the order weight to run the data provider callback.
         *
         * This allows extending classes to run data provider callbacks before or after
         * parent data providers. Lighter weight callbacks run first.
         */
        public readonly int $weight = 0
    ) {
    }
}
