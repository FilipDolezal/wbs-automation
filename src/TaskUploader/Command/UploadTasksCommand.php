<?php

namespace App\TaskUploader\Command;

use App\TaskUploader\Service\RedmineService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UploadTasksCommand extends Command
{
    protected static $defaultName = 'app:upload-tasks';

    public function __construct(private RedmineService $redmineService)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Uploads tasks from an Excel file to Redmine.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Hello from UploadTasksCommand!');
        // Here you would add the logic to parse the Excel file and upload tasks
        // using $this->redmineService.
        return Command::SUCCESS;
    }
}
