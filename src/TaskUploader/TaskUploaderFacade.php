<?php

namespace App\TaskUploader;

use App\TaskUploader\WbsTask;
use App\TaskUploader\Exception\IssueCreationException;
use App\TaskUploader\Exception\RedmineServiceException;
use App\TaskUploader\IssueFactory;

// New import
use App\TaskUploader\RedmineService;
use ReflectionException;
use RuntimeException;

class TaskUploaderFacade
{
    /**
     * Cache of resolved Redmine Issue IDs.
     * Key: A unique string representing the issue.
     * Value: The Redmine Issue ID.
     * @var array<string, int>
     */
    private array $issueCache = [];

    /**
     * @param array<string, string> $wbsMap WBS COLUMN => CUSTOM FIELD NAME
     */
    public function __construct(
        private readonly RedmineService $redmineService,
        private readonly IssueFactory $issueFactory,
        private readonly array $wbsMap,
    ) {
    }

    /**
     * @throws RedmineServiceException
     */
    public function configure(
        string $projectIdentifier,
        string $trackerName,
        string $statusName,
        string $priorityName
    ): void
    {
        $this->issueFactory->configure(
            projectId: $this->redmineService->getProjectIdByIdentifier($projectIdentifier),
            trackerId: $this->redmineService->getTrackerIdByName($trackerName),
            statusId: $this->redmineService->getStatusIdByName($statusName),
            priorityId: $this->redmineService->getPriorityIdByName($priorityName),
            wbsCustomFieldIdMap: $this->redmineService->getCustomFieldIds($this->wbsMap),
        );
    }

    /**
     * @throws IssueCreationException
     */
    public function upload(WbsTask $task): int
    {
        // 1. Resolve Initiative
        $initiativeId = $task->initiative ? $this->getOrUploadParent($task->initiative) : null;

        // 2. Resolve Epic
        $epicId = $task->epic ? $this->getOrUploadParent($task->epic, $initiativeId) : null;

        // 3. Upload the Task itself
        // Parent is Epic if exists, else Initiative, else null.
        $parentId = $epicId ?? $initiativeId;

        // Create Issue DTO using Factory
        $issue = $this->issueFactory->createFromWbsTask($task, $parentId);

        // Use RedmineService to create the task
        return $this->redmineService->createIssue($issue);
    }

    /**
     * Resolves an Issue ID for a parent entity (Initiative/Epic) by name.
     * Checks cache -> checks Redmine -> creates if missing.
     * @throws IssueCreationException
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
        $existingId = $this->redmineService->getIssueIdBySubject($name, $parentId);
        if ($existingId !== null)
        {
            $this->issueCache[$cacheKey] = $existingId;
            return $existingId;
        }

        // 3. Create New
        $issue = $this->issueFactory->createParent($name, $parentId);
        $newId = $this->redmineService->createIssue($issue);

        $this->issueCache[$cacheKey] = $newId;

        return $newId;
    }
}
