<?php

namespace App\TaskUploader\Service;

use App\TaskUploader\Service\Exception\IssueCreationException;
use App\TaskUploader\Service\Exception\ProjectNotFoundException;
use App\TaskUploader\Service\Exception\TrackerNotFoundException;
use RuntimeException;

// New import
use Redmine\Client\Psr18Client;
use SimpleXMLElement;

readonly class RedmineService
{
    public function __construct(private Psr18Client $client)
    {
    }

    public function getTrackerIdByName(string $trackerName): int
    {
        $response = $this->client->getApi('tracker')->list();

        if (!isset($response['trackers']) || !is_array($response['trackers']))
        {
            throw new TrackerNotFoundException("Could not retrieve trackers from Redmine API.");
        }

        foreach ($response['trackers'] as $tracker)
        {
            if ($tracker['name'] === $trackerName)
            {
                return (int)$tracker['id'];
            }
        }

        throw new TrackerNotFoundException(sprintf("Tracker '%s' not found.", $trackerName));
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
     * @throws IssueCreationException
     */
    public function createIssue(
        string $title,
        int $projectId,
        int $trackerId,
        int $priorityId,
        int $statusId,
        ?int $parentId = null,
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

        try
        {
            $response = $this->client->getApi('issue')->create($issueData);

            // Handle SimpleXMLElement response (common with some client configs)
            if ($response instanceof SimpleXMLElement && isset($response->id))
            {
                return (int)$response->id;
            }

            // Handle Array response
            if (is_array($response) && isset($response['issue']['id']))
            {
                return (int)$response['issue']['id'];
            }

            // If 'errors' key exists, format them
            $errorMsg = 'Unknown API error. Response: ' . json_encode($response);

            if (is_array($response) && isset($response['errors']))
            {
                 $errorMsg = json_encode($response['errors']);
            }
            elseif ($response instanceof SimpleXMLElement && isset($response->errors))
            {
                 $errorMsg = json_encode($response->errors);
            }

            throw new IssueCreationException($errorMsg);
        }
        catch (\Exception $e)
        {
            throw new IssueCreationException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws ProjectNotFoundException
     */
    public function getProjectIdByIdentifier(string $projectIdentifier): int
    {
        $response = $this->client->getApi('project')->list();

        if (!isset($response['projects']) || !is_array($response['projects']))
        {
            throw new ProjectNotFoundException("Could not retrieve projects from Redmine API.");
        }

        foreach ($response['projects'] as $project)
        {
            if ($project['identifier'] === $projectIdentifier)
            {
                return (int)$project['id'];
            }
        }

        throw new ProjectNotFoundException(sprintf("Project with identifier '%s' not found.", $projectIdentifier));
    }

    public function getDefaultPriorityId(): int
    {
        $response = $this->client->getApi('issue_priority')->list();

        if (!isset($response['issue_priorities']) || !is_array($response['issue_priorities']))
        {
            throw new RuntimeException("Could not retrieve issue priorities from Redmine API. Response: " . json_encode($response));
        }

        foreach ($response['issue_priorities'] as $priority)
        {
            if (isset($priority['is_default']) && $priority['is_default'])
            {
                return (int)$priority['id'];
            }
        }

        // Fallback to the first one if no default is set
        if (count($response['issue_priorities']) > 0) {
            return (int)$response['issue_priorities'][0]['id'];
        }

        throw new RuntimeException("No issue priorities found in Redmine. Response: " . json_encode($response));
    }

    public function getPriorityIdByName(string $priorityName): int
    {
        $response = $this->client->getApi('issue_priority')->list();

        if (!isset($response['issue_priorities']) || !is_array($response['issue_priorities']))
        {
            throw new RuntimeException("Could not retrieve issue priorities from Redmine API.");
        }

        foreach ($response['issue_priorities'] as $priority)
        {
            if ($priority['name'] === $priorityName)
            {
                return (int)$priority['id'];
            }
        }

        throw new RuntimeException(sprintf("Priority '%s' not found.", $priorityName));
    }

    public function getDefaultStatusId(): int
    {
        $response = $this->client->getApi('issue_status')->all();

        if (!isset($response['issue_statuses']) || !is_array($response['issue_statuses']))
        {
            throw new RuntimeException("Could not retrieve issue statuses from Redmine API. Response: " . json_encode($response));
        }

        foreach ($response['issue_statuses'] as $status)
        {
            if (isset($status['is_default']) && $status['is_default'])
            {
                return (int)$status['id'];
            }
        }

        // Fallback to the first one if no default is set
        if (count($response['issue_statuses']) > 0) {
            return (int)$response['issue_statuses'][0]['id'];
        }

        throw new RuntimeException("No issue statuses found in Redmine. Response: " . json_encode($response));
    }
}