<?php

namespace App\TaskUploader;

use App\Common\CommandLogTrait;
use App\Common\ExcelParser\Exception\ExcelParserDefinitionException;
use App\Common\ExcelParser\Exception\ExcelParserException;
use App\Common\ExcelParser\WorksheetTableParser;
use App\Common\ExcelWriter\WorksheetTableWriter;
use App\TaskUploader\Exception\IssueCreationException;
use App\TaskUploader\Parser\WbsColumnDefinition;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

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
    use CommandLogTrait;

    public const string ARG_PROJECT = 'project';
    public const string ARG_FILEPATH = 'filepath';

    public const string OPT_TRACKER = 'tracker';
    public const string OPT_STATUS = 'status';
    public const string OPT_PRIORITY = 'priority';
    public const string OPT_SKIP_ERROR = 'skip-error';
    public const string OPT_EXISTING_TASK_HANDLER = 'existing-task-handler';

    public const string HANDLER_SKIP = 'skip';
    public const string HANDLER_UPDATE = 'update';
    public const string HANDLER_NEW = 'new';

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
        $this->setupIO($input, $output);

        $filePath = $input->getArgument(self::ARG_FILEPATH);
        $project = $input->getArgument(self::ARG_PROJECT);
        $tracker = $input->getOption(self::OPT_TRACKER);
        $status = $input->getOption(self::OPT_STATUS);
        $priority = $input->getOption(self::OPT_PRIORITY);
        $skipError = $input->getOption(self::OPT_SKIP_ERROR);
        $existingTaskHandler = $input->getOption(self::OPT_EXISTING_TASK_HANDLER);

        $this->logConfig('Starting WBS upload process', [
            "Project" => $project,
            "File" => $filePath,
            "Sheet" => $this->worksheetName,
            "Tracker" => $tracker,
            "Status" => $status,
            "Priority" => $priority,
            "Skip Parsing errors" => $skipError ? "Yes" : "No",
            "Existing Task Handler" => $existingTaskHandler
        ]);

        try
        {
            $this->setupWorksheet($filePath);
            $this->taskUploaderFacade->configure($project, $tracker, $status, $priority, $existingTaskHandler);
        }
        catch (Throwable $e)
        {
            $this->logException($e);
            return Command::FAILURE;
        }

        try
        {
            $this->logInfo('Parsing file...');
            $this->parser->parse(!$skipError);
        }
        catch (RuntimeException $e)
        {
            $this->logException($e, 'An error occurred while parsing the file');
            return Command::FAILURE;
        }
        catch (ExcelParserException $e)
        {
            $this->logException($e);
            return Command::FAILURE;
        }

        $results = $this->parser->getResults();
        $parsedTaskCount = count($results);
        $this->logInfo("Successfully parsed [$parsedTaskCount] tasks. Uploading tasks...");

        $progressBar = new ProgressBar($output, $parsedTaskCount);
        $progressBar->start();

        foreach ($results as $rowNumber => $task)
        {
            /** @var string $taskName */
            $taskName = $task->get($this->columns->columnTaskName);

            try
            {
                $redmineId = $this->taskUploaderFacade->upload($task);

                $this->logger->info(sprintf(
                    "[%s/%s] Processed Redmine Issue [%s]: %s",
                    $progressBar->getProgress() + 1,
                    $progressBar->getMaxSteps(),
                    $redmineId,
                    $taskName
                ));

                $this->writer->write($rowNumber, $this->columns->columnRedmineId, $redmineId);
            }
            catch (IssueCreationException $e)
            {
                $this->logException($e, "Unable to create an Issue: $taskName");
            }
            catch (ExcelParserDefinitionException $e)
            {
                $this->logException($e, "Wbs structure error: {$e->getMessage()}");
            }
            finally
            {
                $progressBar->advance();
            }
        }

        $progressBar->finish();

        try
        {
            $pi = pathinfo($filePath);
            $outputFilePath = sprintf("%s/%s_processed.%s", $pi['dirname'], $pi['filename'], $pi['extension']);

            $this->writer->save($outputFilePath);
            $this->logInfo("Processed file saved to: $outputFilePath");
        }
        catch (\Exception $e)
        {
            $this->logException($e, "Failed to save output file: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Uploads tasks from an Excel file to Redmine.')
            ->addArgument(self::ARG_FILEPATH, InputArgument::REQUIRED, 'The path to the Excel file.')
            ->addArgument(self::ARG_PROJECT, InputArgument::REQUIRED, 'The project identifier.')
            ->addOption(self::OPT_TRACKER, 't', InputOption::VALUE_REQUIRED, 'The tracker name to use.', $this->defaultTracker)
            ->addOption(self::OPT_STATUS, 's', InputOption::VALUE_REQUIRED, 'The status name to use.', $this->defaultStatus)
            ->addOption(self::OPT_PRIORITY, 'p', InputOption::VALUE_REQUIRED, 'The priority name to use.', $this->defaultPriority)
            ->addOption(self::OPT_SKIP_ERROR, null, InputOption::VALUE_OPTIONAL, 'Skip parsing errors.', true)
            ->addOption(
                self::OPT_EXISTING_TASK_HANDLER,
                null,
                InputOption::VALUE_REQUIRED,
                sprintf(
                    'How to handle existing tasks found by Redmine ID: %s, %s, %s.',
                    self::HANDLER_SKIP,
                    self::HANDLER_UPDATE,
                    self::HANDLER_NEW
                ),
                self::HANDLER_SKIP
            );
    }

    private function setupWorksheet(string $filePath): void
    {
        // Load spreadsheet once
        if (!file_exists($filePath))
        {
            throw new RuntimeException("Input file not found: $filePath");
        }

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getSheetByName($this->worksheetName);

        if ($worksheet === null)
        {
            throw new RuntimeException("Sheet '{$this->worksheetName}' not found in input file.");
        }

        $this->parser->setWorksheet($worksheet);
        $this->writer->setWorksheet($worksheet);
    }
}
