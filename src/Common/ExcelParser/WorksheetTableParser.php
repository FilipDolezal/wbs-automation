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

class WorksheetTableParser
{
    protected Spreadsheet $spreadsheet;

    protected Worksheet $worksheet;

    protected bool $processed = false;

    /** @var array<int, DynamicRow> */
    protected array $result = [];

    /** @var array<int, ExcelParserException> */
    protected array $failed = [];

    public function __construct(
        protected string $worksheetName,
        protected DynamicColumns $dynamicColumns,
    )
    {
    }

    /**
     * @return array<int, DynamicRow>
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
     * @return array<int, ExcelParserException>
     */
    final public function getFailures(): array
    {
        if (!$this->processed)
        {
            throw new RuntimeException('Results are not available yet! You need to parse the file first.');
        }

        return $this->failed;
    }

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

    final public function parse(OutputInterface $output): void
    {
        $headerRowProcessed = false;

        foreach ($this->worksheet->getRowIterator() as $rowNumber => $row)
        {
            $cellIterator = $row->getCellIterator($this->dynamicColumns->firstColumn, $this->dynamicColumns->lastColumn);
            $cellIterator->setIterateOnlyExistingCells(false);

            if (!$headerRowProcessed)
            {
                $headerRowProcessed = true;

                continue;
            }

            try
            {
                $this->result[$rowNumber] = $this->parseEntity($rowNumber, $cellIterator);
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
     * @throws ExcelParserException
     */
    protected function parseEntity(int $row, CellIterator $cells): DynamicRow
    {
        $dynamicRow = new DynamicRow();

        try
        {
            foreach ($cells as $cell)
            {
                $col = $cell->getColumn();

                if (!$this->dynamicColumns->isDefined($col))
                {
                    continue;
                }

                $definition = $this->dynamicColumns->get($col);

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