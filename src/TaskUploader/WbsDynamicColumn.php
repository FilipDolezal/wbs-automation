<?php

namespace App\TaskUploader;

use App\Common\ExcelParser\DynamicColumn;

class WbsDynamicColumn extends DynamicColumn
{
    /** @var ?string */
    public ?string $field;

    /** @var ?bool */
    public ?bool $custom;
}