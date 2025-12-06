<?php

namespace App\Common\ExcelParser;

class DynamicColumn
{
    public string $column;
    public string $type;
    public bool $nullable;
    public bool $calculated;
}