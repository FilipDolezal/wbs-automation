<?php

namespace App\TaskUploader\Service\Exception;

class IssueNotFoundException extends RedmineServiceException
{
    public function __construct(string $identifier, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Issue not found: " . $identifier, $code, $previous);
    }
}