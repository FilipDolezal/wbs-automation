<?php

namespace App\TaskUploader\Exception;

use Exception;

/**
 * Base exception for errors occurring within the RedmineService.
 *
 * Used for general service failures like missing configuration (Project not found, Tracker not found)
 * or API communication errors.
 */
class RedmineServiceException extends Exception
{
}