<?php

namespace App\TaskUploader;

use App\Common\ExcelParser\DynamicRow;
use App\Common\ExcelParser\Exception\ExcelParserDefinitionException;

readonly class IssueFactory
{
    public function __construct(
        private int $projectId,
        private int $trackerId,
        private int $statusId,
        private int $priorityId,
        private array $customFieldIds,
        private WbsDynamicColumns $columns,
    )
    {
    }

    /**
     * @throws ExcelParserDefinitionException
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
            subject: $task->get($this->columns->getColumn('subject')),
            description: $task->get($this->columns->getColumn('description')),
            parentId: $parentId,
            estimatedHours: $task->get($this->columns->getColumn('estimatedHours')),
            customFields: $customFields
        );
    }

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
