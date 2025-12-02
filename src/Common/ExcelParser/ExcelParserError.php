<?php

namespace App\Common\ExcelParser;

enum ExcelParserError: int
{
    case CellNotFound = 0;
    case UnexpectedNull = 1;
    case UnexpectedType = 2;
    case CalculationError = 3;

    public function message(): string
    {
        return match ($this)
        {
            self::CellNotFound => 'Cell not found',
            self::UnexpectedNull => 'Unexpected null value',
            self::UnexpectedType => 'Unexpected type',
            self::CalculationError => 'Calculation error',
        };
    }
}
