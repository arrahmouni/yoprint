<?php

namespace App\Enums;

enum FileStatusEnums: string
{
    case PENDING    = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED  = 'completed';
    case FAILED     = 'failed';

    public static function values(): array
    {
        return [
            self::PENDING->value,
            self::PROCESSING->value,
            self::COMPLETED->value,
            self::FAILED->value
        ];
    }
}
