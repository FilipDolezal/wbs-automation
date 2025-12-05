<?php

namespace App\TaskUploader\Service;

use App\TaskUploader\Service\Exception\IssueCreationException;
use App\TaskUploader\Service\Exception\ProjectNotFoundException;
use App\TaskUploader\Service\Exception\RedmineServiceException;
use App\TaskUploader\Service\Exception\TrackerNotFoundException;
use Redmine\Client\Psr18Client;
use SimpleXMLElement;

// New import

readonly class RedmineService
{
    public function __construct(private Psr18Client $client)
    {
    }

    /**
     * @throws IssueCreationException
     */
    public function createParentIssue(
        string $title,
        int $projectId,
        int $trackerId,
        int $priorityId,
        int $statusId,
        ?int $parentId = null,
        ?string $description = null,
    ): int
    {
        return $this->createIssue(
            title: $title,
            projectId: $projectId,
            trackerId: $trackerId,
            priorityId: $priorityId,
            statusId: $statusId,
            parentId: $parentId,
            description: $description
        );
    }

    /**
     * @throws IssueCreationException
     */
    public function createIssue(
        string $title,
        int $projectId,
        int $trackerId,
        int $priorityId,
        int $statusId,
        ?int $parentId = null,
        ?string $description = null,
        ?float $estimatedHours = null,
        array $customFields = []
    ): int
    {
        $issueData = [
            'project_id' => $projectId,
            'priority_id' => $priorityId,
            'status_id' => $statusId,
            'subject' => $title,
            'tracker_id' => $trackerId,
        ];

        if (!empty($customFields))
        {
            $issueData['custom_fields'] = $customFields;
        }

        if ($parentId !== null)
        {
            $issueData['parent_issue_id'] = $parentId;
        }

        if (!empty($description))
        {
            $issueData['description'] = $description;
        }

        if ($estimatedHours !== null)
        {
            $issueData['estimated_hours'] = $estimatedHours;
        }

        /** @var SimpleXMLElement|false|string $response */
        $response = $this->client->getApi('issue')->create($issueData);

        if ($response instanceof SimpleXMLElement)
        {
            if (isset($response->id))
            {
                return (int)$response->id;
            }

            if (isset($response->error))
            {
                throw new IssueCreationException(json_encode($response->error, JSON_PARTIAL_OUTPUT_ON_ERROR));
            }
        }

        throw new IssueCreationException("Unknown API call error: $response");
    }

    public function getIssueIdBySubject(
        string $subject,
        ?int $parentId = null,
        ?int $projectId = null,
        ?int $trackerId = null,
    ): ?int
    {
        $options = ['subject' => $subject, 'limit' => 100];

        if ($parentId !== null)
        {
            $options['parent_issue_id'] = $parentId;
        }

        if ($projectId !== null)
        {
            $options['project_id'] = $projectId;
        }

        if ($trackerId !== null)
        {
            $options['tracker_id'] = $trackerId;
        }

        // Redmine API 'subject' filter is usually a "contains" search.
        // We request issues containing the string, then filter locally for an exact match.
        $response = $this->client->getApi('issue')->list($options);

        if (!isset($response['issues']) || !is_array($response['issues']))
        {
            return null;
        }

        foreach ($response['issues'] as $issue)
        {
            if ($issue['subject'] === $subject)
            {
                return (int)$issue['id'];
            }
        }

        return null;
    }

    /**
     * @throws RedmineServiceException
     */
    public function getTrackerIdByName(string $trackerName): int
    {
        $response = $this->client->getApi('tracker')->list();

        if (!isset($response['trackers']) || !is_array($response['trackers']))
        {
            throw new RedmineServiceException("Could not retrieve trackers from Redmine API.");
        }

        foreach ($response['trackers'] as $tracker)
        {
            if ($tracker['name'] === $trackerName)
            {
                return (int)$tracker['id'];
            }
        }

        throw new RedmineServiceException(sprintf("Tracker '%s' not found.", $trackerName));
    }

    /**
     * @throws RedmineServiceException
     */
    public function getProjectIdByIdentifier(string $projectIdentifier): int
    {
        $response = $this->client->getApi('project')->list();

        if (!isset($response['projects']) || !is_array($response['projects']))
        {
            throw new RedmineServiceException("Could not retrieve projects from Redmine API.");
        }

        foreach ($response['projects'] as $project)
        {
            if ($project['identifier'] === $projectIdentifier)
            {
                return (int)$project['id'];
            }
        }

        throw new RedmineServiceException(sprintf("Project with identifier '%s' not found.", $projectIdentifier));
    }

    /**
     * @throws RedmineServiceException
     */
    public function getPriorityIdByName(string $priorityName): int
    {
        $response = $this->client->getApi('issue_priority')->list();

        if (!isset($response['issue_priorities']) || !is_array($response['issue_priorities']))
        {
            throw new RedmineServiceException("Could not retrieve issue priorities from Redmine API.");
        }

        foreach ($response['issue_priorities'] as $priority)
        {
            if ($priority['name'] === $priorityName)
            {
                return (int)$priority['id'];
            }
        }

        throw new RedmineServiceException(sprintf("Priority '%s' not found.", $priorityName));
    }

    /**
     * @throws RedmineServiceException
     */
    public function getStatusIdByName(string $statusName): int
    {
        $response = $this->client->getApi('issue_status')->list();

        if (!isset($response['issue_statuses']) || !is_array($response['issue_statuses']))
        {
            throw new RedmineServiceException("Could not retrieve issue statuses from Redmine API.");
        }

        foreach ($response['issue_statuses'] as $status)
        {
            if ($status['name'] === $statusName)
            {
                return (int)$status['id'];
            }
        }

        throw new RedmineServiceException(sprintf("Status '%s' not found.", $statusName));
    }
}