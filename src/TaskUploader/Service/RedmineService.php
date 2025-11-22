<?php

namespace App\TaskUploader\Service;

use Redmine\Client\Psr18Client;

readonly class RedmineService
{
    public function __construct(public Psr18Client $client)
    {
    }
}