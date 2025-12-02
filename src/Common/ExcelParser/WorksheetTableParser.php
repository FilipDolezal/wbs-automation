<?php

namespace App\Common\ExcelParser;

use PhpOffice\PhpSpreadsheet\Calculation\Exception;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\CellIterator;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Console\Output\OutputInterface;
use RuntimeException;

/**
 * @template T of object
 */
abstract class WorksheetTableParser
{
    protected Spreadsheet $spreadsheet;

    protected Worksheet $worksheet;

    protected bool $processed = false;

    /** @var array<int, T> */
    protected array $result = [];

    /** @var array<int, ExcelParserException> */
    protected array $failed = [];

    /**
     * @return T
     */
    abstract protected function parseEntity(int $row, CellIterator $cells): object;

    abstract protected function getSheetName(): string;

    abstract protected function getColumns(): array;

    /**
     * @return array<int, T>
     */
    public function getResults(): array
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
    public function getFailures(): array
    {
        if (!$this->processed)
        {
            throw new RuntimeException('Results are not available yet! You need to parse the file first.');
        }

        return $this->failed;
    }

    public function open(string $filePath): void
    {
        if (!file_exists($filePath))
        {
            throw new RuntimeException("File not found at path: $filePath");
        }

        $ss = IOFactory::load($filePath, IReader::READ_DATA_ONLY);
        $ws = $ss->getSheetByName($this->getSheetName());

        if ($ws === null)
        {
            throw new RuntimeException("Sheet '{$this->getSheetName()}' not found.");
        }

        $this->spreadsheet = $ss;
        $this->worksheet = $ws;
    }

    public function parse(OutputInterface $output): void
    {
        $headerRowProcessed = false;
        $fstCol = $this->getColumns()[0];
        $lstCol = $this->getColumns()[count($this->getColumns()) - 1];

        foreach ($this->worksheet->getRowIterator() as $rowNumber => $row)
        {
            $cellIterator = $row->getCellIterator($fstCol, $lstCol);
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
            catch (ExcelParserParseException $e)
            {
                $output->writeln(sprintf("<error>Row [%s] Error: %s</error>", $rowNumber, $e->getMessage()));
                $this->failed[$rowNumber] = $e;
            }
        }

        $this->processed = true;
    }

    /**
     * @throws ExcelParserCellException
     */
    protected function getRawStringNullable(array $map, string $col): ?string
    {
        $value = $this->getRawValue($map, $col);

        if ($value === null)
        {
            return null;
        }

        if (!is_string($value))
        {
            throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_TYPE, $col);
        }

        $value = trim($value);

        return empty($value) ? null : $value;
    }

    /**
     * @throws ExcelParserCellException
     */
    protected function getRawString(array $map, string $col): string
    {
        $value = $this->getRawStringNullable($map, $col);

        if (empty($value))
        {
            throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_NULL, $col);
        }

        return $value;
    }

    /**
     * @throws ExcelParserCellException
     */
    protected function getRawIntNullable(array $map, string $col): ?int
    {
        $value = $this->getRawValue($map, $col);

        if (is_numeric($value))
        {
            return (int) $value;
        }

        if (empty($value))
        {
            return null;
        }

        throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_TYPE, $col);
    }

    /**
     * @throws ExcelParserCellException
     */
    protected function getRawInt(array $map, string $col): int
    {
        $value = $this->getRawIntNullable($map, $col);

        if ($value === null)
        {
            throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_NULL, $col);
        }

        return $value;
    }

    /**
     * @throws ExcelParserCellException
     */
    protected function getCalculatedFloatNullable(array $map, string $col): ?float
    {
        $value = $this->getCalculatedValue($map, $col);

        if (is_numeric($value))
        {
            return (float) $value;
        }

        if (empty($value))
        {
            return null;
        }

        throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_TYPE, $col);
    }

    /**
     * @throws ExcelParserCellException
     */
    protected function getCalculatedFloat(array $map, string $col): float
    {
        $value = $this->getCalculatedFloatNullable($map, $col);

        if ($value === null)
        {
            throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_NULL, $col);
        }

        return $value;
    }

    /**
     * @throws ExcelParserCellException
     */
    private function getCell(array $map, string $col): Cell
    {
        return $map[$col] ?? throw new ExcelParserCellException(ExcelParserException::CODE_CELL_NOT_FOUND, $col);
    }

    /**
     * @throws ExcelParserCellException
     */
    private function getRawValue(array $map, string $col): mixed
    {
        return $this->getCell($map, $col)->getValue();
    }

    /**
     * @throws ExcelParserCellException
     */
    private function getCalculatedValue(array $map, string $col): mixed
    {
        try
        {
            return $this->getCell($map, $col)->getCalculatedValue();
        }
        catch (Exception)
        {
            throw new ExcelParserCellException(ExcelParserException::CODE_CALCULATION_ERROR, $col);
        }
    }
}