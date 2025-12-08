<?php

namespace App\Common\ExcelParser\Exception;

use Exception;

/**
 * Base exception for all Excel Parser related errors.
 *
 * Provides a set of standard error codes to categorize failures.
 */
class ExcelParserException extends Exception
{
    /** @var int Error code when a cell cannot be located. */
    public const int CODE_CELL_NOT_FOUND = 100;
    
    /** @var int Error code when a non-nullable column contains a null value. */
    public const int CODE_UNEXPECTED_NULL = 101;
    
    /** @var int Error code when the cell value type does not match the definition. */
    public const int CODE_UNEXPECTED_TYPE = 102;
    
    /** @var int Error code when formula calculation fails. */
    public const int CODE_CALCULATION_ERROR = 103;
    
    /** @var int Error code for duplicate entities (if applicable). */
    public const int CODE_DUPLICATE_ENTITY = 200;
    
    /** @var int Generic error code. */
    public const int CODE_UNKNOWN_ERROR = 999;

    /**
     * Resolves a human-readable message for a given error code.
     *
     * @param int $code
     * @return string
     */
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
