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

/**
 * Orchestrates the task upload process.
 *
 * This facade simplifies the interaction between the Excel parser and the Redmine service.
 * It manages the hierarchy of tasks (Initiative -> Epic -> Task) by resolving
 * parent issues (either from cache or by querying Redmine) before creating new ones.
 */
class TaskUploaderFacade
{
    /** @var IssueFactory Factory for creating Issue DTOs. */
    private readonly IssueFactory $issueFactory;

    /**
     * Cache of resolved Redmine Issue IDs.
     * Key: A serialized unique key representing the issue (parentID + subject).
     * Value: The Redmine Issue ID.
     * @var array<string, int>
     */
    private array $issueCache = [];

    /**
     * @var string How to handle existing tasks found by Redmine ID.
     */
    private string $existingTaskHandler;

    public function __construct(
        private readonly RedmineService $redmineService,
        private readonly WbsColumnDefinition $columns,
    ) {
    }

    /**
     * Prepares the Facade for processing by resolving necessary Redmine IDs.
     *
     * Fetches IDs for Project, Tracker, Status, Priority, and Custom Fields
     * to initialize the IssueFactory.
     *
     * @param string $projectIdentifier
     * @param string $trackerName
     * @param string $statusName
     * @param string $priorityName
     * @throws RedmineServiceException If any configuration entity cannot be found.
     */
    public function configure(
        string $projectIdentifier,
        string $trackerName,
        string $statusName,
        string $priorityName,
        string $existingTaskHandler,
    ): void
    {
        $this->existingTaskHandler = $existingTaskHandler;

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
     * Processes a single WBS row and uploads it to Redmine.
     *
     * Handles the logic for:
     * 1. Resolving/Creating the Initiative (Level 1 Parent).
     * 2. Resolving/Creating the Epic (Level 2 Parent).
     * 3. Creating the Task itself (Child).
     *
     * @param DynamicRow $task The parsed Excel row.
     * @return int The ID of the created task.
     * @throws IssueCreationException If the upload fails.
     * @throws ExcelParserDefinitionException If parsing fails.
     */
    public function upload(DynamicRow $task): int
    {
        // Resolve old Redmine ID
        /** @var ?int $oldRedmineId */
        $oldRedmineId = $task->get($this->columns->columnRedmineId);
        if ($oldRedmineId !== null && $this->existingTaskHandler === UploadTasksCommand::HANDLER_SKIP)
        {
            return $oldRedmineId;
        }

        // Resolve Initiative
        /** @var ?string $initiativeString */
        $initiativeString = $task->get($this->columns->columnInitiative);
        $initiativeId = $initiativeString ? $this->getOrUploadParent($initiativeString) : null;

        // Resolve Epic
        /** @var ?string $epicString */
        $epicString = $task->get($this->columns->columnEpic);
        $epicId = $epicString ? $this->getOrUploadParent($epicString, $initiativeId) : null;

        // Upload the Task itself
        // Parent is Epic if exists, else Initiative, else null.
        $parentId = $epicId ?? $initiativeId;

        // Create Issue DTO using Factory
        $issue = $this->issueFactory->createFromWbsTask($task, $parentId);

        if ($oldRedmineId !== null)
        {
            return match ($this->existingTaskHandler)
            {
                // Update existing task
                UploadTasksCommand::HANDLER_UPDATE => $this->redmineService->updateIssue($oldRedmineId, $issue),

                // Create a new (duplicate task)
                UploadTasksCommand::HANDLER_NEW => $this->redmineService->createIssue($issue),
            };
        }

        // Create a new task
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
