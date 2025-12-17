<?php

namespace App\TaskUploader\Parser;

use App\Common\ExcelParser\Exception\ExcelParserDefinitionException;
use App\Common\ExcelParser\WorksheetTableParser;
use App\Common\ExcelWriter\WorksheetTableWriter;

final readonly class WbsWorksheet
{
    public const string WORKSHEET_NAME = 'name';
    public const string COLUMN_IDENTIFIERS = 'column_identifiers';
    public const string COLUMN_DEFINITION = 'column_definition';

    public string $worksheetName;
    public WbsColumnDefinition $columns;
    public WorksheetTableParser $parser;
    public WorksheetTableWriter $writer;

    /**
     * @param array{name: string, column_identifiers: array, column_definition: array} $configuration
     * @throws ExcelParserDefinitionException
     */
    public function __construct(private array $configuration)
    {
        $name = $this->requireParam(self::WORKSHEET_NAME);
        assert(is_string($name), new ExcelParserDefinitionException('Worksheet name must be a string'));

        $identifiers = $this->requireParam(self::COLUMN_IDENTIFIERS);
        assert(is_array($identifiers), new ExcelParserDefinitionException('Identifiers must be an array'));

        $definition = $this->requireParam(self::COLUMN_DEFINITION);
        assert(is_array($definition), new ExcelParserDefinitionException('Column definition must be an array'));

        $this->worksheetName = $name;
        $this->columns = new WbsColumnDefinition($definition, $identifiers);
        $this->parser = new WorksheetTableParser($this->columns);
        $this->writer = new WorksheetTableWriter();
    }

    /**
     * @throws ExcelParserDefinitionException
     */
    private function requireParam(string $param)
    {
        return $this->configuration[$param]
            ?? throw new ExcelParserDefinitionException("Worksheet name not defined: missing '%param' in definition");
    }
}