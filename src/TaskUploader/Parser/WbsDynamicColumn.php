<?php

namespace App\TaskUploader\Parser;

use App\Common\ExcelParser\DynamicColumn;

/**
 * Represents a column configuration with WBS-specific properties.
 */
class WbsDynamicColumn extends DynamicColumn
{
    /** @var ?string Name of the mapped Redmine field (standard or custom). */
    public ?string $field;

    /** @var ?bool True if this maps to a Redmine Custom Field. */
    public ?bool $custom;
}