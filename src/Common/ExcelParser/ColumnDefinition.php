<?php

namespace App\Common\ExcelParser;

use App\Common\ExcelParser\Exception\ExcelParserDefinitionException;

/**
 * Defines the schema for the Excel columns to be parsed.
 *
 * This class holds the configuration for each column (e.g., type, nullability)
 * and calculates the range (first to last column) to be iterated over.
 *
 * @template T of DynamicColumn
 * @phpstan-type Attributes array<string, string|bool|null>
 */
readonly class ColumnDefinition
{
    /**
     * @var array<string, T> Map of column letter (e.g., 'A') to its definition object.
     */
    protected array $columns;

    /** @var string The first column letter in the defined range. */
    public string $firstColumn;

    /** @var string The last column letter in the defined range. */
    public string $lastColumn;

    /**
     * @param array<string, Attributes> $columnDefinition Configuration array where key is column letter.
     * @throws ExcelParserDefinitionException If the definition array is empty.
     */
    public function __construct(array $columnDefinition)
    {
        $columns = [];

        if (empty($columnDefinition))
        {
            throw new ExcelParserDefinitionException("No columns defined.");
        }

        foreach ($columnDefinition as $column => $attributes)
        {
            $columns[$column] = static::defineColumn($column, $attributes);
        }

        $keys = array_keys($columnDefinition);

        // Sort column keys to determine the correct range (e.g., A, B, ..., Z, AA, AB).
        usort($keys, static function ($a, $b) {
            // 1. Compare Lengths first (so 'A' comes before 'AA')
            // 2. If lengths are equal, compare values ('AA' comes before 'AB')
            return strlen($a) <=> strlen($b) ?: $a <=> $b;
        });

        $this->columns = $columns;
        $this->firstColumn = $keys[0];
        $this->lastColumn = $keys[count($keys) - 1];
    }

    /**
     * Retrieves the definition for a specific column.
     *
     * @param string $column The column letter (e.g., 'A').
     * @return T The column definition object.
     * @throws ExcelParserDefinitionException If the column is not defined.
     */
    final public function get(string $column): DynamicColumn
    {
        if (!isset($this->columns[$column]))
        {
            throw new ExcelParserDefinitionException("Column [$column] not defined.");
        }

        return $this->columns[$column];
    }

    /**
     * Checks if a definition exists for the given column.
     *
     * @param string $column The column letter.
     * @return bool True if defined, false otherwise.
     */
    final public function isDefined(string $column): bool
    {
        return isset($this->columns[$column]);
    }

    /**
     * Factory method to create a DynamicColumn instance from attributes.
     *
     * @param string $column The column letter.
     * @param Attributes $attributes Array of attributes (type, nullable, calculated, etc.).
     * @return T
     * @throws ExcelParserDefinitionException If required attributes (like 'type') are missing.
     */
    public static function defineColumn(string $column, array $attributes): DynamicColumn
    {
        $dynamicColumn = new DynamicColumn();
        $dynamicColumn->column = $column;
        $dynamicColumn->type = $attributes['type'] ?? throw new ExcelParserDefinitionException("Column [$column] doesn't define type.");
        $dynamicColumn->nullable = $attributes['nullable'] ?? true;
        $dynamicColumn->calculated = $attributes['calculated'] ?? false;

        return $dynamicColumn;
    }
}