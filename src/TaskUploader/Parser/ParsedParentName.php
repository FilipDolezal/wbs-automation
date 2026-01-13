<?php

namespace App\TaskUploader\Parser;

/**
 * Represents a parsed parent name that may contain a Redmine ID prefix.
 *
 * Format: "[12345] Name" where 12345 is the Redmine issue ID.
 */
readonly class ParsedParentName
{
    public function __construct(
        public ?int $redmineId,
        public ?string $name
    ) {}

    public function hasRedmineId(): bool
    {
        return $this->redmineId !== null;
    }
}
