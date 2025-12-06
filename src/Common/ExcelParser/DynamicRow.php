<?php

namespace App\Common\ExcelParser;

/**
 * Represents a single parsed row from the Excel file.
 *
 * This class acts as a container for the extracted values, keyed by their
 * column letter.
 */
class DynamicRow
{
    /** @var array<string, mixed> Map of column letter to parsed value. */
    private array $values;

    /**
     * Retrieves the value for a specific column.
     *
     * @param string $column The column letter (e.g., 'A').
     * @return mixed The parsed value, or null if not set.
     */
    public function get(string $column): mixed
    {
        return $this->values[$column] ?? null;
    }

    /**
     * Sets the value for a specific column.
     *
     * @internal This method is intended for internal use by the parser.
     * @param string $column The column letter.
     * @param mixed $value The value to store.
     */
    public function set(string $column, mixed $value): void
    {
        $this->values[$column] = $value;
    }
}