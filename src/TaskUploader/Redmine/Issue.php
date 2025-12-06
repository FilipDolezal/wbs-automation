<?php

namespace App\TaskUploader\Redmine;

/**
 * Data Transfer Object (DTO) representing a Redmine Issue.
 *
 * Encapsulates all the data required to create an issue via the Redmine API.
 */
class Issue
{
    /**
     * @param int $projectId Redmine Project ID.
     * @param int $trackerId Redmine Tracker ID.
     * @param int $statusId Redmine Status ID.
     * @param int $priorityId Redmine Priority ID.
     * @param string $subject The issue subject/title.
     * @param string|null $description The issue description.
     * @param int|null $parentId ID of the parent issue.
     * @param float|null $estimatedHours Estimated hours.
     * @param array $customFields Array of custom fields key-values.
     */
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

    /**
     * Converts the DTO into an array format suitable for the Redmine API client.
     *
     * @return array<string, mixed>
     */
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
