<?php

namespace App\TaskUploader\Parser;

use App\Common\ExcelParser\ExcelColumn;
use App\Common\ExcelParser\RowEntity;

readonly class WbsTask implements RowEntity
{
    #[ExcelColumn(col: 'A')]
    public string $taskName;

    #[ExcelColumn(col: 'B')]
    public ?string $initiative;

    #[ExcelColumn(col: 'C')]
    public ?string $epic;

    #[ExcelColumn(col: 'D')]
    public ?int $redmineId;

    #[ExcelColumn(col: 'F')]
    public ?float $estimatedDevHours;

    #[ExcelColumn(col: 'H', calculated: true)]
    public ?float $overheadHours;

    #[ExcelColumn(col: 'J', calculated: true)]
    public ?float $estimatedTotalHours;

    #[ExcelColumn(col: 'K', calculated: true)]
    public ?float $estimatedFinalHours;

    #[ExcelColumn(col: 'L')]
    public ?string $description;

    #[ExcelColumn(col: 'M')]
    public ?string $acceptanceCriteria;

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

    public static function getSheetName(): string
    {
        return 'WBS - vývoj';
    }
}
