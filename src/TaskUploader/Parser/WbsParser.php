<?php

namespace App\TaskUploader\Parser;

use App\Common\ExcelParser\ExcelParserException;
use App\Common\ExcelParser\ExcelParserParseException;
use App\Common\ExcelParser\RowEntity;
use App\Common\ExcelParser\WorksheetTableParser;
use PhpOffice\PhpSpreadsheet\Worksheet\CellIterator;

/**
 * @extends WorksheetTableParser<WbsTask>
 */
class WbsParser extends WorksheetTableParser
{
    /** @var array<string, int> */
    private array $hashes = [];

    protected function getEntityClass(): string
    {
        return WbsTask::class;
    }

    /**
     * @throws ExcelParserException
     */
    protected function parseEntity(int $row, CellIterator $cells): WbsTask
    {
        /** @var WbsTask $task */
        $task = parent::parseEntity($row, $cells);

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
}