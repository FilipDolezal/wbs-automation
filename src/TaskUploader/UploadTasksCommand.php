<?php

namespace App\TaskUploader;

use App\Common\ExcelParser\Exception\ExcelParserDefinitionException;
use App\Common\ExcelParser\WorksheetTableParser;
use App\TaskUploader\Exception\IssueCreationException;
use App\TaskUploader\Exception\RedmineServiceException;
use App\TaskUploader\Parser\WbsColumnDefinition;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UploadTasksCommand extends Command
{
    public const string ARG_PROJECT = 'project';
    public const string ARG_FILEPATH = 'filepath';
    
    public const string OPT_TRACKER = 'tracker';
    public const string OPT_STATUS = 'status';
    public const string OPT_PRIORITY = 'priority';

    protected static $defaultName = 'app:upload-tasks';

    public function __construct(
        private readonly WorksheetTableParser $parser,
        private readonly TaskUploaderFacade $taskUploaderFacade,
        private readonly LoggerInterface $logger,
        private readonly WbsColumnDefinition $columns,
        private readonly string $defaultTracker,
        private readonly string $defaultStatus,
        private readonly string $defaultPriority,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $filePath = $input->getArgument(self::ARG_FILEPATH);
        $project = $input->getArgument(self::ARG_PROJECT);

        // Resolve configuration (Option > Default)
        $tracker = $input->getOption(self::OPT_TRACKER) ?? $this->defaultTracker;
        $status = $input->getOption(self::OPT_STATUS) ?? $this->defaultStatus;
        $priority = $input->getOption(self::OPT_PRIORITY) ?? $this->defaultPriority;

        $io->title("Starting WBS upload process");
        $io->text([
            "Project: $project",
            "File: $filePath",
            "Tracker: $tracker",
            "Status: $status",
            "Priority: $priority"
        ]);

        $this->logger->info('Starting WBS upload process', [
            'project' => $project,
            'filepath' => $filePath,
            'config' => [
                'tracker' => $tracker,
                'status' => $status,
                'priority' => $priority
            ]
        ]);

        try
        {
            $this->taskUploaderFacade->configure($project, $tracker, $status, $priority);
        }
        catch (RedmineServiceException $e)
        {
            $io->error('Redmine service error: ' . $e->getMessage());
            $this->logger->error('Redmine service error.', ['exception' => $e]);
            return Command::FAILURE;
        }

        try
        {
            $this->parser->open($filePath);
        }
        catch (RuntimeException $e)
        {
            $io->error('An error occurred while opening the file: ' . $e->getMessage());
            $this->logger->critical('An error occurred while opening the file.', ['exception' => $e]);
            return Command::FAILURE;
        }

        $this->parser->parse($output);

        foreach ($this->parser->getResults() as $task)
        {
            $taskName = $task->get($this->columns->getColumnByIdentifier(WbsColumnDefinition::ID_TASK_NAME));

            try
            {
                $redmineId = $this->taskUploaderFacade->upload($task);
                $io->info("Created new Redmine Issue [$redmineId]: $taskName");
            }
            catch (IssueCreationException $e)
            {
                $io->error("Unable to create an Issue: $taskName");
                $this->logger->critical('Unable to create an Issue.', ['exception' => $e]);
                continue;
            }
            catch (ExcelParserDefinitionException $e)
            {
                $io->error("Wbs structure error: {$e->getMessage()}");
                $this->logger->error('Wbs structure error.', ['exception' => $e]);
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
            ->addArgument(self::ARG_PROJECT, InputArgument::REQUIRED, 'The project identifier.')
            ->addOption(self::OPT_TRACKER, 't', InputOption::VALUE_OPTIONAL, 'The tracker name to use.', null)
            ->addOption(self::OPT_STATUS, 's', InputOption::VALUE_OPTIONAL, 'The status name to use.', null)
            ->addOption(self::OPT_PRIORITY, 'p', InputOption::VALUE_OPTIONAL, 'The priority name to use.', null);
    }
}
