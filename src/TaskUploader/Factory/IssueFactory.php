<?php

namespace App\TaskUploader\Factory;

use App\TaskUploader\DTO\Issue;
use App\TaskUploader\Parser\WbsTask;
use RuntimeException;

class IssueFactory
{
    private ?int $projectId = null;
    private ?int $trackerId = null;
    private ?int $statusId = null;
    private ?int $priorityId = null;

    public function configure(int $projectId, int $trackerId, int $statusId, int $priorityId): void
    {
        $this->projectId = $projectId;
        $this->trackerId = $trackerId;
        $this->statusId = $statusId;
        $this->priorityId = $priorityId;
    }

    public function createFromWbsTask(WbsTask $task, ?int $parentId = null): Issue
    {
        $this->ensureConfigured();

        return new Issue(
            projectId: $this->projectId,
            trackerId: $this->trackerId,
            statusId: $this->statusId,
            priorityId: $this->priorityId,
            subject: $task->taskName,
            description: $task->description,
            parentId: $parentId,
            estimatedHours: $task->estimatedFinalHours
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
