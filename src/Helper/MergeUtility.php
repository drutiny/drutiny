<?php

namespace Drutiny\Helper;

class MergeUtility
{
  /**
   * Recursively merge overrides into the source array.
   */
    public static function arrayMerge(array $base, array ...$arrays): array
    {
        foreach ($arrays as $overrides) {
            foreach ($overrides as $key => $override) {
                if (is_array($override) && isset($base[$key]) && is_array($base[$key])) {
                    $base[$key] = self::arrayMerge($base[$key], $override);
                    continue;
                }
                $base[$key] = $override;
            }
        }
        return $base;
    }
}
