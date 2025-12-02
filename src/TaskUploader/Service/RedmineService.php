<?php

namespace App\TaskUploader\Service;

use App\TaskUploader\Service\Exception\IssueCreationException;
use App\TaskUploader\Service\Exception\TrackerNotFoundException;

// New import
use Redmine\Client\Psr18Client;

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
        ?int $parentId = null,
        array $customFields = []
    ): int
    {
        $issueData = [
            'project_id' => $projectId,
            'subject' => $title,
            'tracker_id' => $trackerId,
            'custom_fields' => $customFields,
        ];

        if ($parentId !== null)
        {
            $issueData['parent_issue_id'] = $parentId; // Redmine API uses 'parent_issue_id'
        }

        try
        {
            $response = $this->client->getApi('issue')->create($issueData);

            if (isset($response['issue']['id']))
            {
                return (int)$response['issue']['id'];
            }

            // If 'errors' key exists, format them
            $errorMsg = isset($response['errors']) ? json_encode($response['errors']) : 'Unknown API error';
            throw new IssueCreationException($errorMsg);
        }
        catch (\Exception $e)
        {
            throw new IssueCreationException($e->getMessage(), 0, $e);
        }
    }
}