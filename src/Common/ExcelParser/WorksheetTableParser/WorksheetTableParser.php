<?php

namespace App\Common\ExcelParser\WorksheetTableParser;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

readonly class WorksheetTableParser
{
    protected Worksheet $worksheet;

    protected string $fstCol;

    protected string $lstCol;

    public function __construct(
        protected WorksheetTableStructure $structure
    )
    {
        $this->fstCol = $structure->getColumns()[0];
        $this->lstCol = $structure->getColumns()[count($structure->getColumns()) - 1];
    }

    public function open(string $filePath): void
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

        $this->worksheet = $ws;
    }

    public function parse(): array
    {
        $tasks = [];
        $headerRowProcessed = false;

        foreach ($this->worksheet->getRowIterator() as $row)
        {
            $rowData = [];
            $cellIterator = $row->getCellIterator($this->fstCol, $this->lstCol);
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

            $tasks[] = array_merge(
                array_fill_keys($this->structure->getColumns(), null),
                array_intersect_key($rowData, array_flip($this->structure->getColumns()))
            );
        }

        return $tasks;
    }
}