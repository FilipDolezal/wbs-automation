<?php

namespace App\Common\ExcelParser;

use Exception;
use JetBrains\PhpStorm\Pure;

class ExcelParserException extends Exception
{
    public function __construct(public ExcelParserError $error)
    {
        parent::__construct($error->message());
    }
}