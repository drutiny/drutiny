<?php

namespace Drutiny\Plugin;

enum FieldType
{
    case CONFIG;
    case CREDENTIAL;
    case STATE;
    
    public function key():string
    {
        return match ($this) {
            self::CONFIG => 'config',
            self::CREDENTIAL => 'credential',
            self::STATE => 'state',
        };
    }

    public static function get(string $type):FieldType
    {
        return match ($type) {
            'config' => self::CONFIG,
            'credential' => self::CREDENTIAL,
            'state' => self::STATE,
        };
    }
}
