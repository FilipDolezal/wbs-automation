<?php

namespace App\TaskUploader\Command;

use App\TaskUploader\Parser\WbsParser;
use App\TaskUploader\Service\Exception\IssueCreationException;
use App\TaskUploader\Service\Exception\RedmineServiceException;
use App\TaskUploader\TaskUploaderFacade;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UploadTasksCommand extends Command
{
    public const string ARG_PROJECT = 'project';
    public const string ARG_FILEPATH = 'filepath';
    protected static $defaultName = 'app:upload-tasks';

    public function __construct(
        // private readonly RedmineService $redmineService,
        private readonly WbsParser $wbsParser,
        private readonly TaskUploaderFacade $taskUploaderFacade,
        private readonly LoggerInterface $logger
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument(self::ARG_FILEPATH);
        $project = $input->getArgument(self::ARG_PROJECT);

        $io->title("Starting WBS upload process");
        $this->logger->info('Starting WBS upload process', [
            'project' => $input->getArgument(self::ARG_PROJECT),
            'filepath' => $input->getArgument(self::ARG_FILEPATH),
        ]);

        try
        {
            $this->taskUploaderFacade->configure($project);
        }
        catch (RedmineServiceException $e)
        {
            $io->error('Redmine service error: ' . $e->getMessage());
            $this->logger->error('Redmine service error.', ['exception' => $e]);
            return Command::FAILURE;
        }

        try
        {
            $this->wbsParser->open($filePath);
        }
        catch (RuntimeException $e)
        {
            $io->error('An error occurred while opening the file: ' . $e->getMessage());
            $this->logger->critical('An error occurred while opening the file.', ['exception' => $e]);
            return Command::FAILURE;
        }

        $this->wbsParser->parse($output);

        foreach ($this->wbsParser->getResults() as $task)
        {
            try
            {
                $redmineId = $this->taskUploaderFacade->upload($task);
                $io->info("Created new Redmine Issue [$redmineId]: $task->taskName");
            }
            catch (IssueCreationException $e)
            {
                $io->error("Unable to create an Issue: $task->taskName");
                $this->logger->critical('Unable to create an Issue.', ['exception' => $e]);
                continue;
            }
        }

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Uploads tasks from an Excel file to Redmine.')
            ->addArgument(self::ARG_FILEPATH, InputArgument::REQUIRED, 'The path to the Excel file.')
            ->addArgument(self::ARG_PROJECT, InputArgument::REQUIRED, 'The project identifier.');
    }
}
