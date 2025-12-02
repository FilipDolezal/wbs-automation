<?php

namespace App\Common\ExcelParser\WbsParser;

readonly class WbsTask
{
    public string $hash;

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
        $this->hash = hash('xxh128', serialize([$this->initiative, $this->epic, $this->taskName]));
    }
}