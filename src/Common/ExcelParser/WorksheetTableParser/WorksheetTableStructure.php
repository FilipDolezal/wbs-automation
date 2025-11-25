<?php

namespace App\Common\ExcelParser\WorksheetTableParser;


use App\Common\ExcelParser\ExcelParserError;

interface WorksheetTableStructure
{
    public function getSheetName(): string;

    /** @return string[] */
    public function getColumns(): array;

    public function validate(mixed $value, string $column): ?ExcelParserError;
}