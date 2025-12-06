<?php

namespace App\Common\ExcelParser;

use App\Common\ExcelParser\Exception\ExcelParserCellException;
use App\Common\ExcelParser\Exception\ExcelParserDefinitionException;
use App\Common\ExcelParser\Exception\ExcelParserException;
use App\Common\ExcelParser\Exception\ExcelParserParseException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Exception as PhpOfficeSpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\CellIterator;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Parses an Excel worksheet into a collection of DynamicRow objects.
 *
 * This class uses PhpSpreadsheet to open and iterate through an Excel file.
 * It validates each cell against the provided ColumnDefinition and handles
 * errors gracefully, collecting valid rows in the result and errors in the failed list.
 */
class WorksheetTableParser
{
    /** @var Spreadsheet The loaded PhpSpreadsheet object. */
    protected Spreadsheet $spreadsheet;

    /** @var Worksheet The specific worksheet being parsed. */
    protected Worksheet $worksheet;

    /** @var bool Flag indicating if the parsing process has completed. */
    protected bool $processed = false;

    /** @var array<int, DynamicRow> Successfully parsed rows, indexed by row number. */
    protected array $result = [];

    /** @var array<int, ExcelParserException> Exceptions encountered during parsing, indexed by row number. */
    protected array $failed = [];

    /**
     * @param string $worksheetName The name of the sheet tab to parse.
     * @param ColumnDefinition $columns The schema definition for the columns.
     */
    public function __construct(
        protected string $worksheetName,
        protected ColumnDefinition $columns,
    )
    {
    }

    /**
     * Returns the successfully parsed rows.
     *
     * @return array<int, DynamicRow>
     * @throws RuntimeException If the file hasn't been parsed yet.
     */
    final public function getResults(): array
    {
        if (!$this->processed)
        {
            throw new RuntimeException('Results are not available yet! You need to parse the file first.');
        }

        return $this->result;
    }

    /**
     * Returns the exceptions encountered during parsing.
     *
     * @return array<int, ExcelParserException>
     * @throws RuntimeException If the file hasn't been parsed yet.
     */
    final public function getFailures(): array
    {
        if (!$this->processed)
        {
            throw new RuntimeException('Results are not available yet! You need to parse the file first.');
        }

        return $this->failed;
    }

    /**
     * Opens the Excel file and selects the target worksheet.
     *
     * @param string $filePath Path to the Excel file.
     * @throws RuntimeException If the file does not exist or the sheet is missing.
     */
    final public function open(string $filePath): void
    {
        if (!file_exists($filePath))
        {
            throw new RuntimeException("File not found at path: $filePath");
        }

        $ss = IOFactory::load($filePath, IReader::READ_DATA_ONLY);
        $ws = $ss->getSheetByName($this->worksheetName);

        if ($ws === null)
        {
            throw new RuntimeException("Sheet '$this->worksheetName' not found.");
        }

        $this->spreadsheet = $ss;
        $this->worksheet = $ws;
    }

    /**
     * Iterates through the worksheet rows and parses them.
     *
     * Skips the first row (header). Errors are logged to the output and stored
     * in the failures list, allowing the process to continue for subsequent rows.
     *
     * @param OutputInterface $output Console output for logging errors.
     */
    final public function parse(OutputInterface $output): void
    {
        $headerRowProcessed = false;

        foreach ($this->worksheet->getRowIterator() as $rowNumber => $row)
        {
            $cellIterator = $row->getCellIterator($this->columns->firstColumn, $this->columns->lastColumn);
            $cellIterator->setIterateOnlyExistingCells(false);

            if (!$headerRowProcessed)
            {
                $headerRowProcessed = true;

                continue;
            }

            try
            {
                $this->result[$rowNumber] = $this->parseRow($cellIterator);
            }
            catch (ExcelParserCellException $e)
            {
                $output->writeln(sprintf("<error>Row [%s] %s</error>", $rowNumber, $e->getMessage()));
                $this->failed[$rowNumber] = $e;
            }
            catch (ExcelParserException $e)
            {
                $output->writeln(sprintf("<error>Row [%s] Error: %s</error>", $rowNumber, $e->getMessage()));
                $this->failed[$rowNumber] = $e;
            }
        }

        $this->processed = true;
    }

    /**
     * Parses a single row of cells into a DynamicRow object.
     *
     * @param CellIterator $cells Iterator for the cells in the current row.
     * @return DynamicRow The populated data object.
     * @throws ExcelParserException If a cell fails validation or extracting.
     */
    protected function parseRow(CellIterator $cells): DynamicRow
    {
        $dynamicRow = new DynamicRow();

        try
        {
            foreach ($cells as $cell)
            {
                $col = $cell->getColumn();

                if (!$this->columns->isDefined($col))
                {
                    continue;
                }

                $definition = $this->columns->get($col);

                $value = $this->extractValue($cell, $definition);

                $dynamicRow->set($col, $value);
            }
        }
        catch (PhpOfficeSpreadsheetException $e)
        {
            throw new ExcelParserParseException("Failed to iterate cells", ExcelParserException::CODE_UNKNOWN_ERROR, $e);
        }

        return $dynamicRow;
    }

    /**
     * Extracts and validates the value from a single cell.
     *
     * Checks for:
     * - Nullability violations
     * - Type mismatches (int, float, string)
     * - Calculation errors
     *
     * @param Cell $cell The cell to extract from.
     * @param DynamicColumn $definition The schema definition for the column.
     * @return mixed The extracted value.
     * @throws ExcelParserException
     */
    private function extractValue(Cell $cell, DynamicColumn $definition): mixed
    {
        try
        {
            $col = $cell->getColumn();
        }
        catch (PhpOfficeSpreadsheetException $e)
        {
            throw new ExcelParserParseException(code: ExcelParserException::CODE_CELL_NOT_FOUND, previous: $e);
        }

        try
        {
            $val = $definition->calculated ? $cell->getCalculatedValue() : $cell->getValue();
        }
        catch (\Exception $e)
        {
            throw new ExcelParserCellException(ExcelParserException::CODE_CALCULATION_ERROR, $col, $e);
        }

        if ($val === null || $val === '')
        {
            if ($definition->nullable)
            {
                return null;
            }

            throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_NULL, $col);
        }

        if ($definition->type === 'int')
        {
            if (is_numeric($val))
            {
                return (int)$val;
            }

            throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_TYPE, $col);
        }

        if ($definition->type === 'float')
        {
            if (is_numeric($val))
            {
                return (float)$val;
            }

            throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_TYPE, $col);
        }

        if ($definition->type === 'string')
        {
            if (!is_string($val) && !is_numeric($val))
            {
                throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_TYPE, $col);
            }

            $val = trim((string)$val);

            if ($val === '')
            {
                if ($definition->nullable)
                {
                    return null;
                }
                throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_NULL, $col);
            }

            return $val;
        }

        return $val;
    }
}