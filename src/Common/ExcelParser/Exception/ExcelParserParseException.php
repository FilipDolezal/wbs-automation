<?php

namespace App\Common\ExcelParser\Exception;

class ExcelParserParseException extends ExcelParserException
{
    public function __construct(string $message = "", int $code = self::CODE_UNKNOWN_ERROR, ?\Throwable $previous = null)
    {
        // If a specific code is provided but no custom message, try to use the default
        if ($message === "" && $code !== 0) {
            $message = self::getMessageForCode($code);
        }

        parent::__construct($message, $code, $previous);
    }
}
