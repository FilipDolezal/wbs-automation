<?php

require __DIR__ . '/../vendor/autoload.php';

use App\TaskUploader\Service\RedmineService;

$redmineConfig = require __DIR__ . '/../config/redmine.php';

$redmineService = new RedmineService($redmineConfig['url'], $redmineConfig['api_key']);

$client = $redmineService->getClient();

try {
    $currentUser = $client->getApi('current_user')->show();
    if ($currentUser === false) {
        echo "Could not get current user. Please check your Redmine URL and API key.\n";
        exit(1);
    }

    if (isset($currentUser['user'])) {
        echo "Successfully connected to Redmine!\n";
        echo "Current user: " . $currentUser['user']['login'] . "\n";
    } else {
        echo "Could not get current user. Response from Redmine was not as expected.\n";
        print_r($currentUser);
    }
} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
}

