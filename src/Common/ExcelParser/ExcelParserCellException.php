<?php

namespace App\Common\ExcelParser;

class ExcelParserCellException extends ExcelParserException
{
    public function __construct(int $code, public string $cell, ?\Throwable $previous = null)
    {
        $description = self::getMessageForCode($code);
        $message = sprintf('Cell [%s]: %s', $cell, $description);

        parent::__construct($message, $code, $previous);
    }
}
