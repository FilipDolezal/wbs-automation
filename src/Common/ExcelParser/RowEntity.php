<?php

namespace App\Common\ExcelParser;

use ReflectionProperty;

interface RowEntity
{
    public function afterConstruct(): void;

    public static function getSheetName(): string;

    /**
     * @return array<string, array{property: ReflectionProperty, type: string, nullable: bool, calculated: bool}>
     */
    public static function getMapping(): array;
}
