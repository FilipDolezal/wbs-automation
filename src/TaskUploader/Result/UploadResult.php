<?php

namespace App\TaskUploader\Result;

/**
 * Result of uploading a single task row to Redmine.
 *
 * Contains the task's Redmine ID and information about
 * how parent issues (Initiative/Epic) were resolved.
 */
readonly class UploadResult
{
    public function __construct(
        public int $taskRedmineId,
        public ?ParentResolutionResult $initiative,
        public ?ParentResolutionResult $epic
    ) {}
}
