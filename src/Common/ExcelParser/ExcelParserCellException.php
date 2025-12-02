<?php

namespace App\Common\ExcelParser;

class ExcelParserCellException extends ExcelParserException
{
    public function __construct(ExcelParserError $error, public string $cell)
    {
        parent::__construct($error);
    }
}