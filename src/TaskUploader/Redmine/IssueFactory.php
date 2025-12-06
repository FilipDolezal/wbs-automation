<?php

namespace App\TaskUploader\Redmine;

use App\Common\ExcelParser\DynamicRow;
use App\Common\ExcelParser\Exception\ExcelParserDefinitionException;
use App\TaskUploader\Parser\WbsColumnDefinition;

/**
 * Factory responsible for creating Issue DTOs.
 *
 * It combines configuration data (Project, Tracker, Status, etc.) with
 * data parsed from the WBS Excel file (via DynamicRow).
 */
readonly class IssueFactory
{
    public function __construct(
        private int $projectId,
        private int $trackerId,
        private int $statusId,
        private int $priorityId,
        private array $customFieldIds,
        private WbsColumnDefinition $columns,
    )
    {
    }

    /**
     * Creates a fully populated Issue DTO from a parsed WBS task row.
     *
     * @param DynamicRow $task The parsed row data.
     * @param int|null $parentId The ID of the parent issue (if any).
     * @return Issue
     * @throws ExcelParserDefinitionException If mapped columns are missing.
     */
    public function createFromWbsTask(DynamicRow $task, ?int $parentId = null): Issue
    {
        $customFields = [];

        foreach ($this->customFieldIds as $col => $id)
        {
            $value = $task->get($col);

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
            subject: $task->get($this->columns->getField('subject')),
            description: $task->get($this->columns->getField('description')),
            parentId: $parentId,
            estimatedHours: $task->get($this->columns->getField('estimatedHours')),
            customFields: $customFields
        );
    }

    /**
     * Creates a simplified Issue DTO for a parent entity (Initiative/Epic).
     *
     * Parent entities are created on the fly and primarily need a subject.
     *
     * @param string $subject The name of the Initiative or Epic.
     * @param int|null $parentId The ID of the parent issue (e.g., Initiative ID for an Epic).
     * @return Issue
     */
    public function createParent(string $subject, ?int $parentId = null): Issue
    {
        return new Issue(
            projectId: $this->projectId,
            trackerId: $this->trackerId,
            statusId: $this->statusId,
            priorityId: $this->priorityId,
            subject: $subject,
            parentId: $parentId
        );
    }
}
