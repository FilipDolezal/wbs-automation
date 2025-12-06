<?php

namespace App\Common\ExcelParser;

/**
 * Data Transfer Object representing the configuration of a single Excel column.
 *
 * Instances of this class are created by ColumnDefinition based on the
 * provided configuration array.
 */
class DynamicColumn
{
    /** @var string The column letter (e.g., 'A', 'AB'). */
    public string $column;

    /** @var string The expected data type (e.g., 'string', 'int', 'float'). */
    public string $type;

    /** @var bool Whether the cell value can be null/empty. */
    public bool $nullable;

    /** @var bool Whether the value should be retrieved as a calculated value (formulas). */
    public bool $calculated;
}