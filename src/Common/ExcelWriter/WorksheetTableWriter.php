<?php

namespace App\Common\ExcelWriter;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception as PhpOfficeSpreadsheetWriterException;
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
    private Spreadsheet $spreadsheet;
    private Worksheet $worksheet;
    private string $outputPath;

    public function __construct()
    {
    }

    /**
     * Sets the Spreadsheet object to be used for writing.
     *
     * @param Spreadsheet $inputSpreadsheet The Spreadsheet object.
     * @param string $worksheetName The name of the worksheet to write to.
     * @param string $output The output file path.
     * @throws RuntimeException If the target worksheet is not found within the provided spreadsheet.
     */
    public function setSpreadsheet(Spreadsheet $inputSpreadsheet, string $worksheetName, string $output): void
    {
        $this->spreadsheet = $inputSpreadsheet;
        $worksheet = $this->spreadsheet->getSheetByName($worksheetName);

        if ($worksheet === null)
        {
            throw new RuntimeException("Sheet '{$worksheetName}' not found in the provided Spreadsheet.");
        }

        $this->worksheet = $worksheet;
        $this->outputPath = $output;
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
     * Saves the modified spreadsheet to the output path defined in open().
     *
     * @throws PhpOfficeSpreadsheetWriterException
     */
    public function save(): void
    {
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($this->outputPath);
    }
}
