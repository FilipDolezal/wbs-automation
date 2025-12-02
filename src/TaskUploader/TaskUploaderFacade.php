<?php

namespace App\TaskUploader;

use App\TaskUploader\Parser\WbsTask;
use App\TaskUploader\Service\Exception\IssueCreationException;
use App\TaskUploader\Service\Exception\TrackerNotFoundException;

// New import
use App\TaskUploader\Service\RedmineService;
use RuntimeException;

class TaskUploaderFacade
{
    private int $projectId;
    private int $trackerId;

    /**
     * Cache of resolved Redmine Issue IDs.
     * Key: A unique string representing the issue.
     * Value: The Redmine Issue ID.
     * @var array<string, int>
     */
    private array $issueCache = [];

    public function __construct(private readonly RedmineService $redmineService)
    {
    }

    /**
     * @throws TrackerNotFoundException
     */
    public function configure(int $projectId): void
    {
        // TODO: getProjectIdByIdentifier
        $this->projectId = $projectId;
        $this->trackerId = $this->redmineService->getTrackerIdByName('Požadavek');
    }

    /**
     * @throws IssueCreationException
     */
    public function upload(WbsTask $task): int
    {
        $this->ensureConfigured();

        // 1. Resolve Initiative
        $initiativeId = $task->initiative ? $this->getOrUploadParent($task->initiative) : null;

        // 2. Resolve Epic
        $epicId = $task->epic ? $this->getOrUploadParent($task->epic, $initiativeId) : null;

        // 3. Upload the Task itself
        // Parent is Epic if exists, else Initiative, else null.
        $parentId = $epicId ?? $initiativeId;

        // Use RedmineService to create the task
        return $this->redmineService->createIssue(
            title: $task->taskName,
            projectId: $this->projectId,
            trackerId: $this->trackerId,
            parentId: $parentId,
            customFields: [] // Add custom field mapping logic here
        );
    }

    /**
     * Resolves an Issue ID for a parent entity (Initiative/Epic) by name.
     * Checks cache -> checks Redmine -> creates if missing.
     */
    private function getOrUploadParent(string $name, ?int $parentId = null): int
    {
        $cacheKey = serialize([$parentId, mb_strtolower(trim($name))]);

        // 1. Check Local Cache
        if (isset($this->issueCache[$cacheKey]))
        {
            return $this->issueCache[$cacheKey];
        }

        // 2. Check Remote (Redmine)
        $existingId = $this->redmineService->getIssueIdBySubject($name, $parentId, $this->projectId, $this->trackerId);
        if ($existingId !== null)
        {
            $this->issueCache[$cacheKey] = $existingId;
            return $existingId;
        }

        // 3. Create New
        $newId = $this->redmineService->createIssue(
            title: $name,
            projectId: $this->projectId,
            trackerId: $this->trackerId,
            parentId: $parentId
        );

        $this->issueCache[$cacheKey] = $newId;

        return $newId;
    }

    private function ensureConfigured(): void
    {
        if (!isset($this->projectId))
        {
            throw new RuntimeException("TaskUploaderFacade not configured. Call configure() first.");
        }
    }
}
