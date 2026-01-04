<?php

namespace App\TaskUploader\Parser;

use App\Common\ExcelParser\Exception\ExcelParserDefinitionException;

final readonly class WbsWorksheetRegistry
{
    /**
     * @throws ExcelParserDefinitionException
     */
    public function __construct(private array $worksheetDefinitions)
    {
        if (empty($this->worksheetDefinitions))
        {
            throw new ExcelParserDefinitionException('No worksheets were defined');
        }
    }

    /**
     * @throws ExcelParserDefinitionException
     */
    public function getWorksheet(string $name): WbsWorksheet
    {
        $configuration = array_find(
            array: $this->worksheetDefinitions,
            callback: fn ($v, $k) => $v[WbsWorksheet::WORKSHEET_NAME] === $name
        );

        if ($configuration === null)
        {
            throw new ExcelParserDefinitionException("Worksheet not defined: $name");
        }

        return new WbsWorksheet($configuration);
    }
}