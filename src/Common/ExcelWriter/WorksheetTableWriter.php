<?php

namespace App\Common\ExcelWriter;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Writes data back to an Excel worksheet.
 *
 * This class allows modifying a copy of an existing Excel file by writing values
 * to specific cells.
 */
class WorksheetTableWriter
{
    private Worksheet $worksheet;
    private string $outputFilePath;

    public function setWorksheet(Worksheet $worksheet): void
    {
        $this->worksheet = $worksheet;
    }

    public function getOutputFilePath(): string
    {
        return $this->outputFilePath;
    }

    public function setOutputFilePath(string $outputFilePath): void
    {
        $this->outputFilePath = $outputFilePath;
    }

    /**
     * Writes a value to a specific cell in the configured worksheet.
     *
     * @param int $row The row number (1-based).
     * @param string $column The column letter (e.g., 'A', 'D').
     * @param mixed $value The value to write.
     */
    public function write(int $row, string $column, mixed $value): void
    {
        $this->worksheet->setCellValue($column . $row, $value);
    }

    /**
     * @throws Exception
     */
    public function save(): void
    {
        if (!isset($this->outputFilePath))
        {
            throw new RuntimeException('Output file path is not set.');
        }

        $xlsx = new Xlsx($this->worksheet->getParent());
        $xlsx->save($this->outputFilePath);
    }
}
