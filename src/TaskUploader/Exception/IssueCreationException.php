<?php

namespace App\TaskUploader\Exception;

/**
 * Exception thrown when the creation of a Redmine Issue fails.
 *
 * This can happen due to API errors (e.g., validation failure) or connectivity issues.
 */
class IssueCreationException extends RedmineServiceException
{
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Failed to create issue: " . $message, $code, $previous);
    }
}