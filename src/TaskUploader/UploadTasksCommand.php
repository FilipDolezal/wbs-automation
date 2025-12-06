<?php

namespace App\TaskUploader;

use App\Common\ExcelParser\Exception\ExcelParserDefinitionException;
use App\Common\ExcelParser\WorksheetTableParser;
use App\Common\ExcelWriter\WorksheetTableWriter;
use App\TaskUploader\Exception\IssueCreationException;
use App\TaskUploader\Exception\RedmineServiceException;
use App\TaskUploader\Parser\WbsColumnDefinition;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI Command to upload tasks from an Excel file to Redmine.
 *
 * Usage:
 *   bin/cli app:upload-tasks <filepath> <project_identifier> [options]
 *
 * Options:
 *   --tracker, -t  Override the default tracker name.
 *   --status, -s   Override the default status name.
 *   --priority, -p Override the default priority name.
 */
class UploadTasksCommand extends Command
{
    public const string ARG_PROJECT = 'project';
    public const string ARG_FILEPATH = 'filepath';

    public const string OPT_TRACKER = 'tracker';
    public const string OPT_STATUS = 'status';
    public const string OPT_PRIORITY = 'priority';

    protected static $defaultName = 'app:upload-tasks';

    public function __construct(
        private readonly string $worksheetName,
        private readonly WorksheetTableParser $parser,
        private readonly WorksheetTableWriter $writer,
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

    /**
     * Executes the command logic.
     *
     * 1. Initializes the UI (SymfonyStyle).
     * 2. Configures the TaskUploaderFacade with project settings.
     * 3. Parses the Excel file.
     * 4. Iterates through results and uploads each task.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int Command::SUCCESS or Command::FAILURE
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $filePath = $input->getArgument(self::ARG_FILEPATH);
        $project = $input->getArgument(self::ARG_PROJECT);

        // Resolve configuration (Option > Default)
        $tracker = $input->getOption(self::OPT_TRACKER) ?? $this->defaultTracker;
        $status = $input->getOption(self::OPT_STATUS) ?? $this->defaultStatus;
        $priority = $input->getOption(self::OPT_PRIORITY) ?? $this->defaultPriority;

        try
        {
            // Load spreadsheet once
            if (!file_exists($filePath))
            {
                throw new RuntimeException("Input file not found: $filePath");
            }

            $spreadsheet = IOFactory::load($filePath, IReader::READ_DATA_ONLY);
            $worksheet = $spreadsheet->getSheetByName($this->worksheetName);

            if ($worksheet === null)
            {
                throw new RuntimeException("Sheet '{$this->worksheetName}' not found in input file.");
            }

            // Set parser's worksheet
            $this->parser->setWorksheet($worksheet);

            // Prepare Writer
            $path = pathinfo($filePath);
            $outputFilePath = sprintf("%s/%s_processed.%s", $path['dirname'], $path['filename'], $path['extension']);

            // Set writer's spreadsheet and output path
            $this->writer->setSpreadsheet($spreadsheet, $this->worksheetName, $outputFilePath);
        }
        catch (RuntimeException $e)
        {
            $io->error('An error occurred while opening/preparing the file: ' . $e->getMessage());
            $this->logger->critical('An error occurred while opening/preparing the file.', ['exception' => $e]);
            return Command::FAILURE;
        }

        $io->title("Starting WBS upload process");
        $io->text([
            "Project: $project",
            "File: $filePath",
            "Sheet: $this->worksheetName",
            "Tracker: $tracker",
            "Status: $status",
            "Priority: $priority"
        ]);

        $this->logger->info('Starting WBS upload process', [
            'project' => $project,
            'filepath' => $filePath,
            'sheet' => $this->worksheetName,
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

        $this->parser->parse($output);

        $redmineIdColumn = $this->columns->getColumnByIdentifier(WbsColumnDefinition::ID_REDMINE_ID);

        foreach ($this->parser->getResults() as $rowNumber => $task)
        {
            $taskName = $task->get($this->columns->getColumnByIdentifier(WbsColumnDefinition::ID_TASK_NAME));

            try
            {
                $redmineId = $this->taskUploaderFacade->upload($task);
                $io->info("Created new Redmine Issue [$redmineId]: $taskName");

                if ($redmineIdColumn !== null)
                {
                    $this->writer->write($rowNumber, $redmineIdColumn, $redmineId);
                }
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

        try
        {
            $this->writer->save();
            $io->success("Processed file saved to: $outputFilePath");
        }
        catch (\Exception $e)
        {
            $io->error("Failed to save output file: " . $e->getMessage());
            $this->logger->critical('Failed to save output file.', ['exception' => $e]);
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
