<?php

namespace App\TaskUploader\Parser;

use App\Common\ExcelParser\Attribute\ExcelColumn;
use App\Common\ExcelParser\RowEntity;

class WbsTask implements RowEntity
{
    public const string SHEET_NAME = 'WBS - vývoj';

    #[ExcelColumn(col: 'A')]
    public string $taskName;

    #[ExcelColumn(col: 'B')]
    public ?string $initiative = null;

    #[ExcelColumn(col: 'C')]
    public ?string $epic = null;

    #[ExcelColumn(col: 'D')]
    public ?int $redmineId = null;

    #[ExcelColumn(col: 'F')]
    public ?float $estimatedDevHours = null;

    #[ExcelColumn(col: 'H', calculated: true)]
    public ?float $overheadHours = null;

    #[ExcelColumn(col: 'J', calculated: true)]
    public ?float $estimatedTotalHours = null;

    #[ExcelColumn(col: 'K', calculated: true)]
    public ?float $estimatedFinalHours = null;

    #[ExcelColumn(col: 'L')]
    public ?string $description = null;

    #[ExcelColumn(col: 'M')]
    public ?string $acceptanceCriteria = null;

    public string $hash;

    public ?string $parent;

    public function afterConstruct(): void
    {
        $this->hash = hash('xxh128', serialize([
            mb_strtolower(trim((string)$this->initiative)),
            mb_strtolower(trim((string)$this->epic)),
            mb_strtolower(trim($this->taskName))
        ]));

        $this->parent = $this->epic ?: $this->initiative ?: null;
    }
}
