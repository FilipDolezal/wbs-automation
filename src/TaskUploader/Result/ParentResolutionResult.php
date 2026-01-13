<?php

namespace App\TaskUploader\Result;

/**
 * Result of resolving a parent issue (Initiative or Epic).
 *
 * Contains information about whether the parent was newly created
 * or already existed (either provided by user or found in Redmine).
 */
readonly class ParentResolutionResult
{
    public function __construct(
        public int $redmineId,
        public string $originalName
    ) {}
}
