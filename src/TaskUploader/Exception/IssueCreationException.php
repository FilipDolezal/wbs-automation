<?php

namespace App\TaskUploader\Exception;

class IssueCreationException extends RedmineServiceException
{
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Failed to create issue: " . $message, $code, $previous);
    }
}