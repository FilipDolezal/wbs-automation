<?php

namespace App\Common\ExcelParser;

enum ExcelParserError
{
    case UnexpectedNull;
    case UnexpectedType;
}
