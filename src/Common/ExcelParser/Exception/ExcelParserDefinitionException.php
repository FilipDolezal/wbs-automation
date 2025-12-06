<?php

namespace App\Common\ExcelParser\Exception;

/**
 * Exception thrown when there is an error in the column definitions.
 *
 * This typically occurs during the initialization of ColumnDefinition if the
 * provided configuration is invalid (e.g., missing type, empty definition).
 */
class ExcelParserDefinitionException extends ExcelParserException
{

}