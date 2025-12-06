<?php

namespace App\Common\ExcelParser\Exception;

/**
 * Exception thrown when a specific cell fails validation or parsing.
 *
 * This exception includes the cell coordinate (e.g., 'A1') and a descriptive
 * message derived from the error code.
 */
class ExcelParserCellException extends ExcelParserException
{
    /**
     * @param int $code One of the ExcelParserException::CODE_* constants.
     * @param string $cell The cell coordinate (e.g., 'A1').
     * @param \Throwable|null $previous Optional previous exception.
     */
    public function __construct(int $code, public string $cell, ?\Throwable $previous = null)
    {
        $description = self::getMessageForCode($code);
        $message = sprintf('Cell [%s]: %s', $cell, $description);

        parent::__construct($message, $code, $previous);
    }
}
