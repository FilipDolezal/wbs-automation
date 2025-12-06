<?php

namespace App\Common\ExcelParser;

use App\Common\ExcelParser\Exception\ExcelParserDefinitionException;

/**
 * @template T of DynamicColumn
 * @phpstan-type Attributes array<string, string|bool|null>
 */
readonly class ColumnDefinition
{
    /** @var array<string, T> */
    protected array $columns;
    public string $firstColumn;
    public string $lastColumn;

    /**
     * @param array<string, Attributes> $columnDefinition
     * @throws ExcelParserDefinitionException
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
     * @throws ExcelParserDefinitionException
     * @return T
     */
    final public function get(string $column): DynamicColumn
    {
        if (!isset($this->columns[$column]))
        {
            throw new ExcelParserDefinitionException("Column [$column] not defined.");
        }

        return $this->columns[$column];
    }

    final public function isDefined(string $column): bool
    {
        return isset($this->columns[$column]);
    }

    /**
     * @param Attributes $attributes
     * @throws ExcelParserDefinitionException
     * @return T
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