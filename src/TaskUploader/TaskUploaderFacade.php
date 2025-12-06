<?php

namespace App\TaskUploader;

use App\Common\ExcelParser\DynamicRow;
use App\Common\ExcelParser\Exception\ExcelParserDefinitionException;
use App\TaskUploader\Exception\IssueCreationException;
use App\TaskUploader\Exception\RedmineServiceException;
use App\TaskUploader\Parser\WbsColumnDefinition;
use App\TaskUploader\Parser\WbsDynamicColumn;
use App\TaskUploader\Redmine\IssueFactory;
use App\TaskUploader\Redmine\RedmineService;

class TaskUploaderFacade
{
    private readonly IssueFactory $issueFactory;

    /**
     * Cache of resolved Redmine Issue IDs.
     * Key: A unique string representing the issue.
     * Value: The Redmine Issue ID.
     * @var array<string, int>
     */
    private array $issueCache = [];

    public function __construct(
        private readonly RedmineService $redmineService,
        private readonly WbsColumnDefinition $columns,
    ) {
    }

    /**
     * @throws RedmineServiceException
     */
    public function configure(
        string $projectIdentifier,
        string $trackerName,
        string $statusName,
        string $priorityName,
    ): void
    {
        /** @var array<string, string> $customFields EXCEL COLUMN => REDMINE CUSTOM FIELD NAME */
        $customFields = array_map(static fn (WbsDynamicColumn $c) => $c->field, $this->columns->getCustomFields());

        /** @var array<string, int> $customFieldIds EXCEL COLUMN => REDMINE CUSTOM FIELD ID */
        $customFieldIds = $this->redmineService->getCustomFieldIds($customFields);

        $this->issueFactory = new IssueFactory(
            projectId: $this->redmineService->getProjectIdByIdentifier($projectIdentifier),
            trackerId: $this->redmineService->getTrackerIdByName($trackerName),
            statusId: $this->redmineService->getStatusIdByName($statusName),
            priorityId: $this->redmineService->getPriorityIdByName($priorityName),
            customFieldIds: $customFieldIds,
            columns: $this->columns,
        );
    }

    /**
     * @throws IssueCreationException
     * @throws ExcelParserDefinitionException
     */
    public function upload(DynamicRow $task): int
    {
        // 1. Resolve Initiative
        /** @var ?string $initiativeString */
        $initiativeString = $task->get($this->columns->getColumnByIdentifier(WbsColumnDefinition::ID_INITIATIVE));
        $initiativeId = $initiativeString ? $this->getOrUploadParent($initiativeString) : null;

        // 2. Resolve Epic
        /** @var ?string $epicString */
        $epicString = $task->get($this->columns->getColumnByIdentifier(WbsColumnDefinition::ID_EPIC));
        $epicId = $epicString ? $this->getOrUploadParent($epicString, $initiativeId) : null;

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
