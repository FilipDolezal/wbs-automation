<?php

namespace App\Common\ExcelParser\WbsParser;

readonly class WbsTask
{
    public string $hash;
    public ?string $parentHash;

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

        if ($this->initiative === null && $this->epic === null)
        {
            // initiative does not have parent hash
            $this->parentHash = null;
        }
        else if ($this->initiative !== null && $this->epic === null)
        {
            // parent hash for epic
            $this->parentHash = hash('xxh128', serialize([null, null, $this->initiative]));
        }
        else
        {
            // parent hash for task
            $this->parentHash = hash('xxh128', serialize([$this->initiative, null, $this->epic]));
        }

        // hash signature of this task
        $this->hash = hash('xxh128', serialize([$this->initiative, $this->epic, $this->taskName]));
    }
}