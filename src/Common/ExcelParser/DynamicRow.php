<?php

namespace App\Common\ExcelParser;

class DynamicRow
{
    /** @var array<string, mixed> */
    private array $values;

    public function get(string $column): mixed
    {
        return $this->values[$column] ?? null;
    }

    /** @internal */
    public function set(string $column, mixed $value): void
    {
        $this->values[$column] = $value;
    }
}