<?php

namespace Drutiny\Helper;

use InvalidArgumentException;

class TextCleaner
{
    /**
     * Clean a JSON object or array reponse.
     */
    public static function decodeDirtyJson(string $output): mixed
    {
        $has_array = strpos($output, '[');
        $has_object = strpos($output, '{');

        // We don't know how to clean something that is not an array or object.
        if ($has_array === false && $has_object === false) {
            return json_decode($output, true, JSON_THROW_ON_ERROR);
        }

        if ($has_array === false) {
            $open = '{';
            $close = '}';
        } elseif ($has_object === false) {
            $open = '[';
            $close = ']';
        } else {
            // Figure out if the array or object came first.
            $open = min($has_array, $has_object) == $has_array ? '[' : '{';
            $close = min($has_array, $has_object) == $has_array ? ']' : '}';
        }

        while (true) {
            $result = json_decode($output, true, JSON_THROW_ON_ERROR);

            if ($result === null) {
                $cleaned = substr($output, strpos($output, $open));

                // If the cleaned output looks like the beginning of a
                // JSON object, lets also ensure the output at the end
                // is clean too.
                if (substr($cleaned, 0, 1) == $open) {
                    $rev = strrev($cleaned);
                    $rev = substr($rev, strpos($rev, $close));

                    // Ignore the term '{main}' which comes from stack traces in PHP errors.
                    if (strpos($rev, '}niam{') === 0) {
                        $rev = substr($rev, 1);
                        $rev = substr($rev, strpos($rev, $close));
                    }
                    $cleaned = strrev(substr($rev, strpos($rev, $close)));
                }

                // Remove garbage from beginning of line and try again.
                if ($cleaned != $output) {
                    $output = $cleaned;
                    continue;
                }
            }
            return $result;
        }
    }

    public static function machineValue(string $text): string
    {
        return str_replace(' ', '_', preg_replace('/[^a-z0-9\. ]/', '', strtolower($text)));
    }
}
