<?php

namespace App\TaskUploader\Parser;

use App\Common\ExcelParser\ExcelParserException;
use App\Common\ExcelParser\ExcelParserParseException;
use App\Common\ExcelParser\WorksheetTableParser;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Worksheet\CellIterator;

class WbsParser extends WorksheetTableParser
{
    /** @var array<string, true> */
    private array $hashes = [];

    /**
     * @throws ExcelParserException
     */
    protected function parseEntity(int $row, CellIterator $cells): object
    {
        /** @var array<string, Cell> $raw */
        $raw = [];

        try
        {
            foreach ($cells as $cell)
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

        if (isset($this->hashes[$task->hash]))
        {
            throw new ExcelParserParseException(
                sprintf("Rows %d is a duplicate of row %d: %s", $row, $this->hashes[$task->hash], $task->taskName),
                ExcelParserException::CODE_DUPLICATE_ENTITY
            );
        }

        $this->hashes[$task->hash] = $row;

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