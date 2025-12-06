<?php

namespace App\TaskUploader;

use App\Common\ExcelParser\ColumnDefinition;
use App\TaskUploader\Exception\IssueCreationException;
use RuntimeException;

class IssueFactory
{
    private ?int $projectId = null;
    private ?int $trackerId = null;
    private ?int $statusId = null;
    private ?int $priorityId = null;

    /** @var array<string, ColumnDefinition> $wbsColumnDefinition */
    private array $wbsColumnDefinition;

    /** @var array<string, int> $wbsCustomFieldIdMap */
    private array $wbsCustomFieldIdMap;

    /**
     * @param array<string, int> $wbsCustomFieldIdMap WBS STRUCTURE COLUMN => CUSTOM FIELD ID map
     */
    public function configure(int $projectId, int $trackerId, int $statusId, int $priorityId, array $wbsCustomFieldIdMap): void
    {
        $this->projectId = $projectId;
        $this->trackerId = $trackerId;
        $this->statusId = $statusId;
        $this->priorityId = $priorityId;

        $this->wbsCustomFieldIdMap = $wbsCustomFieldIdMap;
        $this->wbsColumnDefinition = ColumnDefinition::fromEntity(WbsTask::class);
    }

    /**
     * @throws IssueCreationException
     */
    public function createFromWbsTask(WbsTask $task, ?int $parentId = null): Issue
    {
        $this->ensureConfigured();

        $customFields = [];
        foreach ($this->wbsCustomFieldIdMap as $col => $id)
        {
            $columnDefinition = $this->wbsColumnDefinition[$col] ?? null;

            if ($columnDefinition === null)
            {
                throw new IssueCreationException("Column definition not found for column [$col] in WbsTask.");
            }

            $value = $columnDefinition->getValueOf($task);

            if (!empty($value))
            {
                $customFields[] = ['value' => $value, 'id' => $id];
            }
        }

        return new Issue(
            projectId: $this->projectId,
            trackerId: $this->trackerId,
            statusId: $this->statusId,
            priorityId: $this->priorityId,
            subject: $task->taskName,
            description: $task->description,
            parentId: $parentId,
            estimatedHours: $task->estimatedFinalHours,
            customFields: $customFields
        );
    }

    public function createParent(string $subject, ?int $parentId = null): Issue
    {
        $this->ensureConfigured();

        return new Issue(
            projectId: $this->projectId,
            trackerId: $this->trackerId,
            statusId: $this->statusId,
            priorityId: $this->priorityId,
            subject: $subject,
            parentId: $parentId
        );
    }

    private function ensureConfigured(): void
    {
        if ($this->projectId === null || $this->trackerId === null || $this->statusId === null || $this->priorityId === null) {
            throw new RuntimeException("IssueFactory not configured. Call configure() first.");
        }
    }
}
