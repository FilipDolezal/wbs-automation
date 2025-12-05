<?php

namespace App\Common\ExcelParser;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\CellIterator;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ReflectionProperty;
use Symfony\Component\Console\Output\OutputInterface;
use RuntimeException;

abstract class WorksheetTableParser
{
    protected Spreadsheet $spreadsheet;

    protected Worksheet $worksheet;

    protected bool $processed = false;

    /** @var array<int, RowEntity> */
    protected array $result = [];

    /** @var array<int, ExcelParserException> */
    protected array $failed = [];

    /** @var array<string, array{property: ReflectionProperty, type: string, nullable: bool, calculated: bool}> */
    private array $columnMapping = [];

    /**
     * @return class-string<RowEntity>
     */
    abstract protected function getEntityClass(): string;

    /**
     * @return array<int, RowEntity>
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
        $ws = $ss->getSheetByName($this->getEntityClass()->getSheetName());

        if ($ws === null)
        {
            throw new RuntimeException("Sheet '{$this->getEntityClass()->getSheetName()}' not found.");
        }

        $this->spreadsheet = $ss;
        $this->worksheet = $ws;
    }

    public function parse(OutputInterface $output): void
    {
        if (empty($this->columnMapping))
        {
            $class = $this->getEntityClass();
            $this->columnMapping = $class::getMapping();
        }

        $headerRowProcessed = false;
        $columns = array_keys($this->columnMapping);
        sort($columns);

        if (empty($columns))
        {
            throw new RuntimeException("No columns defined for entity " . $this->getEntityClass());
        }

        $fstCol = $columns[0];
        $lstCol = $columns[count($columns) - 1];

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
    protected function parseEntity(int $row, CellIterator $cells): object
    {
        $className = $this->getEntityClass();
        $entity = new $className();

        try
        {
            foreach ($cells as $cell)
            {
                $col = $cell->getColumn();

                if (!isset($this->columnMapping[$col]))
                {
                    continue;
                }

                $map = $this->columnMapping[$col];

                $value = $this->extractValue($cell, $map['type'], $map['nullable'], $map['calculated']);

                $map['property']->setValue($entity, $value);
            }

            if ($entity instanceof RowEntity)
            {
                $entity->afterConstruct();
            }
        }
        catch (\PhpOffice\PhpSpreadsheet\Exception $e)
        {
            throw new ExcelParserParseException("Failed to iterate cells", ExcelParserException::CODE_UNKNOWN_ERROR, $e);
        }

        return $entity;
    }

    /**
     * @throws ExcelParserException
     */
    private function extractValue(Cell $cell, string $targetType, bool $nullable, bool $calculated): mixed
    {
        try
        {
            $col = $cell->getColumn();
        }
        catch (\PhpOffice\PhpSpreadsheet\Exception $e)
        {
            throw new ExcelParserParseException(ExcelParserException::CODE_CELL_NOT_FOUND);
        }

        try
        {
            $val = $calculated ? $cell->getCalculatedValue() : $cell->getValue();
        }
        catch (\Exception $e)
        {
            throw new ExcelParserCellException(ExcelParserException::CODE_CALCULATION_ERROR, $col);
        }

        if ($val === null || $val === '')
        {
            if ($nullable)
            {
                return null;
            }

            throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_NULL, $col);
        }

        if ($targetType === 'int')
        {
            if (is_numeric($val))
            {
                return (int)$val;
            }

            throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_TYPE, $col);
        }

        if ($targetType === 'float')
        {
            if (is_numeric($val))
            {
                return (float)$val;
            }

            throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_TYPE, $col);
        }

        if ($targetType === 'string')
        {
            if (!is_string($val) && !is_numeric($val))
            {
                throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_TYPE, $col);
            }

            $val = trim((string)$val);

            if ($val === '')
            {
                if ($nullable)
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