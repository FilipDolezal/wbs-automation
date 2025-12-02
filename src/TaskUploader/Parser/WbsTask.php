<?php

namespace App\TaskUploader\Parser;

readonly class WbsTask
{
    public string $hash;
    public ?string $parent;

    public function __construct(
        public string $taskName,
        public ?string $epic,
        public ?string $initiative,
        public ?int $redmineId,
        public ?float $estimatedDevHours,
        public ?float $overheadHours,
        public ?float $estimatedTotalHours,
        public ?float $estimatedFinalHours,
        public ?string $description,
        public ?string $acceptanceCriteria,
    )
    {
        $this->hash = hash('xxh128', serialize([
            mb_strtolower(trim((string)$this->initiative)),
            mb_strtolower(trim((string)$this->epic)),
            mb_strtolower(trim($this->taskName))
        ]));

        $this->parent = $this->epic ?: $this->initiative ?: null;
    }
}