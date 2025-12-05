<?php

namespace App\Common\ExcelParser;

use App\Common\ExcelParser\Attribute\ExcelColumn;
use PhpOffice\PhpSpreadsheet\Calculation\Exception;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\CellIterator;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\Console\Output\OutputInterface;
use RuntimeException;

/**
 * @template T of object
 */
abstract class WorksheetTableParser
{
    /** @var class-string<RowEntity> $entityClass */
    protected static string $entityClass;

    protected Spreadsheet $spreadsheet;

    protected Worksheet $worksheet;

    protected bool $processed = false;

    /** @var array<int, T> */
    protected array $result = [];

    /** @var array<int, ExcelParserException> */
    protected array $failed = [];

    /**
     * @return class-string<T>
     */
    abstract protected function getEntityClass(): string;

    abstract protected function getSheetName(): string;

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
        $columns = $this->getColumns();

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
            catch (ExcelParserParseException $e)
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
        $rc = new ReflectionClass(static::$entityClass);
        $properties = $rc->getProperties(); // get only ExcelColumns

        /** @var T $entity */
        $entity = $rc->newInstance();

        try
        {
            foreach ($cells as $cell)
            {
                $col = $cell->getColumn();

                // here you append each attribute to the $entity object

            }

            $entity->afterConstruct();
        }
        catch (\PhpOffice\PhpSpreadsheet\Exception $e)
        {
            throw new ExcelParserParseException("Failed to iterate cells", ExcelParserException::CODE_UNKNOWN_ERROR, $e);
        }


        foreach ($rc->getProperties() as $property)
        {
            $attributes = $property->getAttributes(ExcelColumn::class);
            if (empty($attributes))
            {
                continue;
            }

            /** @var ExcelColumn $attr */
            $attr = $attributes[0]->newInstance();
            $col = $attr->col;
            $isCalculated = $attr->calculated;

            $type = $property->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'string';
            $allowsNull = $type->allowsNull();

            $value = null;

            if ($typeName === 'int')
            {
                $value = $allowsNull
                    ? $this->getRawIntNullable($raw, $col)
                    : $this->getRawInt($raw, $col);
            }
            elseif ($typeName === 'float')
            {
                if ($isCalculated)
                {
                    $value = $allowsNull
                        ? $this->getCalculatedFloatNullable($raw, $col)
                        : $this->getCalculatedFloat($raw, $col);
                }
                else
                {
                    $value = $allowsNull
                        ? $this->getRawFloatNullable($raw, $col)
                        : $this->getRawFloat($raw, $col);
                }
            }
            else
            {
                // Default to string
                $value = $allowsNull
                    ? $this->getRawStringNullable($raw, $col)
                    : $this->getRawString($raw, $col);
            }

            $property->setValue($entity, $value);
        }

        return $entity;
    }

    protected function getColumns(): array
    {
        $columns = [];
        $rc = new ReflectionClass($this->getEntityClass());
        foreach ($rc->getProperties() as $property)
        {
            $attributes = $property->getAttributes(ExcelColumn::class);
            if (!empty($attributes))
            {
                /** @var ExcelColumn $attr */
                $attr = $attributes[0]->newInstance();
                $columns[] = $attr->col;
            }
        }
        $columns = array_unique($columns);
        sort($columns);
        return $columns;
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
            return (int)$value;
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
            return (float)$value;
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
    protected function getRawFloatNullable(array $map, string $col): ?float
    {
        $value = $this->getRawValue($map, $col);

        if (is_numeric($value))
        {
            return (float)$value;
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
    protected function getRawFloat(array $map, string $col): float
    {
        $value = $this->getRawFloatNullable($map, $col);

        if ($value === null)
        {
            throw new ExcelParserCellException(ExcelParserException::CODE_UNEXPECTED_NULL, $col);
        }

        return $value;
    }

    /**
     * @throws ExcelParserCellException
     */
    protected function getCell(array $map, string $col): Cell
    {
        return $map[$col] ?? throw new ExcelParserCellException(ExcelParserException::CODE_CELL_NOT_FOUND, $col);
    }

    /**
     * @throws ExcelParserCellException
     */
    protected function getRawValue(array $map, string $col): mixed
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
