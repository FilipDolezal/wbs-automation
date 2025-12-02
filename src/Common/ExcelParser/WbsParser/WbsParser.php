<?php

namespace App\Common\ExcelParser\WbsParser;

use App\Common\ExcelParser\ExcelParserException;
use App\Common\ExcelParser\ExcelParserParseException;
use App\Common\ExcelParser\WorksheetTableParser;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Worksheet\CellIterator;

class WbsParser extends WorksheetTableParser
{
    /** @var array<string, WbsTask> */
    protected array $tasksByHash = [];

    public function test(): void
    {
        var_dump(array_map(static fn($t) => $t->taskName, $this->tasksByHash));
    }

    /**
     * @throws ExcelParserException
     */
    protected function parseEntity(CellIterator $cellIterator): object
    {
        /** @var array<string, Cell> $raw */
        $raw = [];

        try
        {
            foreach ($cellIterator as $cell)
            {
                $raw[$cell->getColumn()] = $cell;
            }
        }
        catch (Exception $e)
        {
            throw new ExcelParserParseException("Failed to iterate cells", ExcelParserException::CODE_UNKNOWN_ERROR, $e);
        }

        $task = new WbsTask(
            taskName: $this->getRawString($raw, WbsStructure::COLUMN_TASK_NAME),
            epic: $this->getRawStringNullable($raw, WbsStructure::COLUMN_EPIC),
            initiative: $this->getRawStringNullable($raw, WbsStructure::COLUMN_INITIATIVE),
            redmineId: $this->getRawIntNullable($raw, WbsStructure::COLUMN_REDMINE_ID),
            estimatedDevHours: $this->getRawInt($raw, WbsStructure::COLUMN_ESTIMATED_DEV_HOURS),
            overheadHours: $this->getCalculatedFloat($raw, WbsStructure::COLUMN_OVERHEAD_HOURS),
            estimatedTotalHours: $this->getCalculatedFloat($raw, WbsStructure::COLUMN_ESTIMATED_TOTAL_HOURS),
            estimatedFinalHours: $this->getCalculatedFloat($raw, WbsStructure::COLUMN_ESTIMATED_FINAL_HOURS),
            description: $this->getRawStringNullable($raw, WbsStructure::COLUMN_DESCRIPTION),
            acceptanceCriteria: $this->getRawStringNullable($raw, WbsStructure::COLUMN_ACCEPTANCE_CRITERIA)
        );

        if (array_key_exists($task->hash, $this->tasksByHash))
        {
            throw new ExcelParserParseException(
                sprintf("Duplicate task found: %s", $task->taskName),
                ExcelParserException::CODE_DUPLICATE_ENTITY
            );
        }

        $this->tasksByHash[$task->hash] = $task;

        return $task;
    }

    protected function getSheetName(): string
    {
        return WbsStructure::SHEET_NAME;
    }

    protected function getColumns(): array
    {
        return WbsStructure::COLUMNS;
    }
}