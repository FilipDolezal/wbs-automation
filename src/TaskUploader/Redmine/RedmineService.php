<?php

namespace App\TaskUploader\Redmine;

use App\TaskUploader\Exception\IssueCreationException;
use App\TaskUploader\Exception\RedmineServiceException;
use Redmine\Client\Psr18Client;
use SimpleXMLElement;

/**
 * Service wrapper for interacting with the Redmine API.
 *
 * Handles creating issues, retrieving configuration IDs (Project, Tracker, etc.),
 * and searching for existing issues.
 */
readonly class RedmineService
{
    public function __construct(private Psr18Client $client)
    {
    }

    /**
     * Sends a request to create a new issue in Redmine.
     *
     * @param Issue $issue The issue DTO.
     * @return int The ID of the newly created issue.
     * @throws IssueCreationException If the API call fails or returns an error.
     */
    public function createIssue(Issue $issue): int
    {
        /** @var SimpleXMLElement|false|string $response */
        $response = $this->client->getApi('issue')->create($issue->toArray());

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

    /**
     * Sends a request to update an existing issue in Redmine.
     *
     * @param int $issueId The ID of the issue to update.
     * @param Issue $issue The issue DTO with updated fields.
     * @return int The ID of the updated issue.
     * @throws IssueCreationException If the API call fails or returns an error.
     */
    public function updateIssue(int $issueId, Issue $issue): int
    {
        /** @var SimpleXMLElement|false|string $response */
        $response = $this->client->getApi('issue')->update($issueId, $issue->toArray());

        if ($response instanceof SimpleXMLElement)
        {
            if (isset($response->issue->id))
            {
                return (int)$response->issue->id;
            }

            if (isset($response->error))
            {
                throw new IssueCreationException(json_encode($response->error, JSON_PARTIAL_OUTPUT_ON_ERROR));
            }
        }

        // The Redmine API for update usually returns an empty body on success.
        // If we reach here without an error or an XML response, assume success and return the original ID.
        if ($response === '')
        {
            return $issueId;
        }

        throw new IssueCreationException("Unknown API call error during update: $response");
    }

    /**
     * Searches for an existing issue by subject (and optional parent).
     *
     * This is used to prevent creating duplicate Initiatives or Epics.
     *
     * @param string $subject The subject to search for.
     * @param int|null $projectId The project ID to search within.
     * @param int|null $parentIssueId Optional parent ID to narrow the search.
     * @return int|null The Issue ID if found, or null.
     */
    public function getIssueIdBySubject(string $subject, ?int $projectId = null, ?int $parentIssueId = null): ?int
    {
        $options = ['subject' => "~$subject", 'limit' => 1];

        if ($parentIssueId !== null)
        {
            $options['parent_issue_id'] = $parentIssueId;
        }

        if ($projectId !== null)
        {
            $options['project_id'] = $projectId;
        }

        $response = $this->client->getApi('issue')->list($options);

        if (!isset($response['issues']) || !is_array($response['issues']))
        {
            return null;
        }

        $issue = array_first($response['issues']);

        return isset($issue['id']) ? (int)$issue['id'] : null;
    }

    /**
     * Retrieves the internal ID of a Tracker by its name.
     *
     * @param string $trackerName
     * @return int
     * @throws RedmineServiceException If the tracker is not found.
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
     * Retrieves the internal ID of a Project by its string identifier.
     *
     * @param string $projectIdentifier
     * @return int
     * @throws RedmineServiceException If the project is not found.
     */
    public function getProjectIdByIdentifier(string $projectIdentifier): int
    {
        $response = $this->client->getApi('project')->show($projectIdentifier);

        /** @var int $id */
        $id = $response['project']['id'] ??
            throw new RedmineServiceException(sprintf("Project with identifier '%s' not found.", $projectIdentifier));

        return $id;
    }

    /**
     * Retrieves the internal ID of a Priority by its name.
     *
     * @param string $priorityName
     * @return int
     * @throws RedmineServiceException If the priority is not found.
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
     * Retrieves the internal ID of a Status by its name.
     *
     * @param string $statusName
     * @return int
     * @throws RedmineServiceException If the status is not found.
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

    /**
     * Resolves internal IDs for a list of Custom Fields.
     *
     * @param array<string, string> $columnToNameMap Map of Excel Column => Custom Field Name.
     * @return array<string, int> Map of Excel Column => Custom Field ID.
     * @throws RedmineServiceException If any custom field cannot be found.
     */
    public function getCustomFieldIds(array $columnToNameMap): array
    {
        $response = $this->client->getApi('custom_fields')->list(['limit' => 100]);

        if (!isset($response['custom_fields']) || !is_array($response['custom_fields']))
        {
            throw new RedmineServiceException("Could not retrieve custom fields from Redmine API.");
        }

        $nameToId = [];
        foreach ($response['custom_fields'] as $field)
        {
            $nameToId[$field['name']] = (int)$field['id'];
        }

        $result = [];
        foreach ($columnToNameMap as $column => $name)
        {
            if (isset($nameToId[$name]))
            {
                $result[$column] = $nameToId[$name];
            }
            else
            {
                throw new RedmineServiceException(sprintf("Custom field '%s' for column '%s' not found in Redmine.", $name, $column));
            }
        }

        return $result;
    }
}