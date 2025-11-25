<?php

namespace App\Common\ExcelParser\WorksheetTableParser;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

readonly class WorksheetTableParser
{
    public function __construct(
        protected WorksheetTableStructure $structure
    )
    {
    }

    public function parse(string $filePath): array
    {
        if (!file_exists($filePath))
        {
            throw new RuntimeException("File not found at path: $filePath");
        }

        $ss = IOFactory::load($filePath);
        $ws = $ss->getSheetByName($this->structure->getSheetName());

        if ($ws === null)
        {
            throw new RuntimeException("Sheet '{$this->structure->getSheetName()}' not found.");
        }

        $tasks = [];
        $headerRowProcessed = false;
        $fstColumn = $this->structure->getColumns()[0];
        $lstColumn = $this->structure->getColumns()[count($this->structure->getColumns()) - 1];

        foreach ($ws->getRowIterator() as $row)
        {
            $rowData = [];
            $cellIterator = $row->getCellIterator($fstColumn, $lstColumn);
            $cellIterator->setIterateOnlyExistingCells(false);

            if (!$headerRowProcessed)
            {
                $headerRowProcessed = true;

                continue;
            }

            foreach ($cellIterator as $column => $cell)
            {
                $rowData[$column] = $raw = $cell->getCalculatedValue();

                if (($err = $this->structure->validate($raw, $column)) !== null)
                {
                    // todo: LOG ERROR;
                    continue 2;
                }
            }

            $processedRowData = array_merge(
                array_fill_keys($this->structure->getColumns(), null),
                array_intersect_key($rowData, array_flip($this->structure->getColumns()))
            );
        }

        return $tasks;
    }
}