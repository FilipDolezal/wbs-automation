<?php

namespace App\Common\ExcelParser\Exception;

use Exception;

abstract class ExcelParserException extends Exception
{
    public const int CODE_CELL_NOT_FOUND = 100;
    public const int CODE_UNEXPECTED_NULL = 101;
    public const int CODE_UNEXPECTED_TYPE = 102;
    public const int CODE_CALCULATION_ERROR = 103;
    public const int CODE_DUPLICATE_ENTITY = 200;
    public const int CODE_UNKNOWN_ERROR = 999;

    protected static function getMessageForCode(int $code): string
    {
        return match ($code) {
            self::CODE_CELL_NOT_FOUND => 'Cell not found',
            self::CODE_UNEXPECTED_NULL => 'Unexpected null value',
            self::CODE_UNEXPECTED_TYPE => 'Unexpected type',
            self::CODE_CALCULATION_ERROR => 'Calculation error',
            self::CODE_DUPLICATE_ENTITY => 'Duplicate entity found',
            default => 'Unknown error',
        };
    }
}
