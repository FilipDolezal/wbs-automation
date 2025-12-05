<?php

namespace App\Common\ExcelParser;

use App\Common\ExcelParser\Exception\ExcelParserCellException;
use App\Common\ExcelParser\Exception\ExcelParserException;
use App\Common\ExcelParser\Exception\ExcelParserParseException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
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

    /** @var array<int, RowEntity> */
    protected array $result = [];

    /** @var array<int, ExcelParserException> */
    protected array $failed = [];

    /** @var array<string, ColumnDefinition> */
    private array $columnMapping = [];

    /** @var class-string<RowEntity> $entityClass */
    protected string $entityClass;

    /**
     * @return array<int, RowEntity>
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
        if (!isset($this->entityClass))
        {
            throw new RuntimeException('Entity class not set');
        }

        try
        {
            $this->columnMapping = ColumnDefinition::fromEntity($this->entityClass);
        }
        catch (ReflectionException $e)
        {
            throw new RuntimeException("There was a problem with column definition", $e);
        }

        if (empty($this->columnMapping))
        {
            throw new RuntimeException("No columns defined for entity " . $this->entityClass);
        }

        if (!file_exists($filePath))
        {
            throw new RuntimeException("File not found at path: $filePath");
        }

        $sheetName = $this->entityClass::getSheetName();
        $ss = IOFactory::load($filePath, IReader::READ_DATA_ONLY);
        $ws = $ss->getSheetByName($sheetName);

        if ($ws === null)
        {
            throw new RuntimeException("Sheet '$sheetName' not found.");
        }

        $this->spreadsheet = $ss;
        $this->worksheet = $ws;
    }

    final public function parse(OutputInterface $output): void
    {
        $headerRowProcessed = false;
        $columns = array_keys($this->columnMapping);
        sort($columns);
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
        $className = $this->entityClass;
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

                $definition = $this->columnMapping[$col];

                $value = $this->extractValue($cell, $definition);

                $definition->property->setValue($entity, $value);
            }

            $entity->afterConstruct();
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
    private function extractValue(Cell $cell, ColumnDefinition $definition): mixed
    {
        try
        {
            $col = $cell->getColumn();
        }
        catch (\PhpOffice\PhpSpreadsheet\Exception $e)
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