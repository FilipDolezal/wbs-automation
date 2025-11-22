<?php

namespace App\TaskUploader\Service;

use Redmine\Client\NativeCurlClient;

class RedmineService
{
    private NativeCurlClient $client;

    public function __construct(string $url, string $apiKey)
    {
        $this->client = new NativeCurlClient($url, $apiKey);
    }

    public function getClient(): NativeCurlClient
    {
        return $this->client;
    }
}
