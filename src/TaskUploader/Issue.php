<?php

namespace App\TaskUploader;

class Issue
{
    public function __construct(
        public int $projectId,
        public int $trackerId,
        public int $statusId,
        public int $priorityId,
        public string $subject,
        public ?string $description = null,
        public ?int $parentId = null,
        public ?float $estimatedHours = null,
        public array $customFields = []
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'project_id' => $this->projectId,
            'tracker_id' => $this->trackerId,
            'status_id' => $this->statusId,
            'priority_id' => $this->priorityId,
            'subject' => $this->subject,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->parentId !== null) {
            $data['parent_issue_id'] = $this->parentId;
        }

        if ($this->estimatedHours !== null) {
            $data['estimated_hours'] = $this->estimatedHours;
        }

        if (!empty($this->customFields)) {
            $data['custom_fields'] = $this->customFields;
        }

        return $data;
    }
}
