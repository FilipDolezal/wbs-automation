<?php

namespace App\Common\ExcelParser\WorksheetTableParser;

use ParseError;

interface WorksheetTableStructure
{
    public function getSheetName(): string;

    /** @return string[] */
    public function getColumns(): array;

    public function validate(mixed $value, string $column): ?ParseError;
}