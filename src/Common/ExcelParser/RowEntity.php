<?php

namespace App\Common\ExcelParser;


interface RowEntity
{
    public function afterConstruct(): void;

    public static function getSheetName(): string;
}
