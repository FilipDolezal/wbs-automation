<?php

namespace App\Common\ExcelParser;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class ExcelColumn
{
    public function __construct(
        public string $col,
        public bool $calculated = false
    ) {}
}
