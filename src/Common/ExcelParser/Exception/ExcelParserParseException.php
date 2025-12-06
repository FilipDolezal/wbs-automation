<?php

namespace App\Common\ExcelParser\Exception;

use Throwable;

/**
 * Exception thrown when a general parsing error occurs.
 *
 * This is used for errors that are not specific to a single cell's validation,
 * such as iteration failures or underlying IO errors.
 */
class ExcelParserParseException extends ExcelParserException
{
    /**
     * @param string $message Custom error message.
     * @param int $code Error code (default: UNKNOWN_ERROR).
     * @param Throwable|null $previous Previous exception.
     */
    public function __construct(string $message = "", int $code = self::CODE_UNKNOWN_ERROR, ?Throwable $previous = null)
    {
        // If a specific code is provided but no custom message, try to use the default
        if ($message === "" && $code !== 0) {
            $message = self::getMessageForCode($code);
        }

        parent::__construct($message, $code, $previous);
    }
}
