<?php

namespace App\TaskUploader\Service\Exception;

class TrackerNotFoundException extends RedmineServiceException
{
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
