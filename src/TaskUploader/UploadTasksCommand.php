<?php

namespace App\TaskUploader;

use App\Common\Command\CommandLogTrait;
use App\Common\ExcelParser\Exception\ExcelParserDefinitionException;
use App\Common\ExcelParser\Exception\ExcelParserException;
use App\TaskUploader\Exception\IssueCreationException;
use App\TaskUploader\Exception\IssueSkipException;
use App\TaskUploader\Exception\RedmineServiceException;
use App\TaskUploader\Parser\WbsWorksheet;
use App\TaskUploader\Parser\WbsWorksheetRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
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
final class UploadTasksCommand extends Command
{
    use CommandLogTrait;

    public const string ARG_SPREADSHEET = 'spreadsheet';
    public const string ARG_WORKSHEET = 'worksheet';
    public const string ARG_PROJECT = 'project';

    public const string OPT_TRACKER = 'tracker';
    public const string OPT_STATUS = 'status';
    public const string OPT_PRIORITY = 'priority';
    public const string OPT_SKIP_PARSE_ERROR = 'skip-parse-error';
    public const string OPT_SKIP_ZERO_ESTIMATE = 'skip-zero-estimate';
    public const string OPT_OUTPUT_FILE = 'output-file';
    public const string OPT_EXISTING_TASK_HANDLER = 'existing-task-handler';

    public const string HANDLER_SKIP = 'skip';
    public const string HANDLER_UPDATE = 'update';
    public const string HANDLER_NEW = 'new';

    protected static $defaultName = 'app:upload-tasks';

    private WbsWorksheet $wbs;

    public function __construct(
        private readonly WbsWorksheetRegistry $wbsWorksheetRegistry,
        private readonly TaskUploaderFacade $taskUploaderFacade,
        private readonly LoggerInterface $logger,
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
        try
        {
            if ($this->setup($input, $output))
            {
                $this->io->success('Starting WBS parsing to Redmine Issues');
            }
            else
            {
                $this->logInfo('Exiting without parsing: User aborted.');
                return Command::SUCCESS;
            }
        }
        catch (Throwable $e)
        {
            $this->logException($e);
            return Command::FAILURE;
        }

        try
        {
            $this->logInfo('Parsing file...');
            $this->wbs->parser->parse();
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

        $results = $this->wbs->parser->getResults();
        $parsedTaskCount = count($results);
        $this->logInfo("Successfully parsed [$parsedTaskCount] tasks. Uploading tasks...");

        $progressBar = new ProgressBar($output, $parsedTaskCount);
        $progressBar->start();

        foreach ($results as $rowNumber => $task)
        {
            /** @var string $taskName */
            $taskName = $task->get($this->wbs->columns->columnTaskName);

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

                $this->wbs->writer->write($rowNumber, $this->wbs->columns->columnRedmineId, $redmineId);
            }
            catch (IssueCreationException $e)
            {
                $this->logException($e, "Unable to create an Issue: $taskName");
            }
            catch (ExcelParserDefinitionException $e)
            {
                $this->logException($e, "Wbs structure error: {$e->getMessage()}");
            }
            catch (IssueSkipException $e)
            {
                $this->logInfo("$taskName: {$e->getMessage()}");
            }
            finally
            {
                $progressBar->advance();
            }
        }

        $progressBar->finish();

        try
        {
            $this->wbs->writer->save();
            $this->logInfo("Processed file saved to: {$this->wbs->writer->getOutputFilePath()}");
        }
        catch (Throwable $e)
        {
            $this->logException($e, "Failed to save output file: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @throws RedmineServiceException
     * @throws ExcelParserDefinitionException
     */
    private function setup(InputInterface $input, OutputInterface $output): bool
    {
        $autoConfirm = true;

        $this->setupIO($input, $output);

        $filePath = $input->getArgument(self::ARG_SPREADSHEET);
        while (empty($filePath))
        {
            $autoConfirm = false;
            $filePath = $this->io->ask('Provide path to the spreadsheet file:');

            if (!file_exists($filePath))
            {
                $this->io->error("Input file not found: $filePath");
                $filePath = null;
            }
        }

        if (!file_exists($filePath))
        {
            throw new InvalidArgumentException("Input file not found: $filePath");
        }

        $spreadsheet = IOFactory::load($filePath);
        $worksheetName = $input->getArgument(self::ARG_WORKSHEET);
        if (empty($worksheetName))
        {
            $autoConfirm = false;

            do
            {
                $worksheetName = $this->io->ask('Provide name of the worksheet:');
                $worksheet = $spreadsheet->getSheetByName($worksheetName);
            }
            while ($worksheet === null);
        }
        else
        {
            $worksheet = $spreadsheet->getSheetByName($worksheetName);
        }

        if ($worksheet === null)
        {
            throw new InvalidArgumentException("Sheet '$worksheetName' not found in input file.");
        }

        $this->wbs = $this->wbsWorksheetRegistry->getWorksheet($worksheetName);
        $this->wbs->writer->setWorksheet($worksheet);
        $this->wbs->parser->setWorksheet($worksheet);

        $project = $input->getArgument(self::ARG_PROJECT);
        if ($project === null)
        {
            $autoConfirm = false;

            do
            {
                $project = $this->io->ask('Provide project identifier:');
            }
            while (empty($project));
        }

        $tracker = $input->getOption(self::OPT_TRACKER);
        $status = $input->getOption(self::OPT_STATUS);
        $priority = $input->getOption(self::OPT_PRIORITY);
        $skipParseError = $input->getOption(self::OPT_SKIP_PARSE_ERROR);
        $skipZeroEstimate = $input->getOption(self::OPT_SKIP_ZERO_ESTIMATE);
        $existingTaskHandler = $input->getOption(self::OPT_EXISTING_TASK_HANDLER);
        $outputFilePath = $input->getOption(self::OPT_OUTPUT_FILE);

        if (!$autoConfirm)
        {
            $this->io->info('Default option configuration:');
            $this->ioTable([
                "Tracker" => $tracker,
                "Status" => $status,
                "Priority" => $priority,
                "Skip Parsing errors" => $skipParseError ? "Yes" : "No",
                "Skip Zero Estimated Hours" => $skipZeroEstimate ? "Yes" : "No",
                "Existing Task Handler" => $existingTaskHandler,
                "Output file path" => $outputFilePath ?? 'Overrides input file'
            ]);
            $change = $this->io->confirm("Do you want to change this configuration?", false);

            if ($change)
            {
                $tracker = $this->io->ask("Tracker name", $this->defaultTracker);
                $status = $this->io->ask("Status name", $this->defaultStatus);
                $priority = $this->io->ask("Priority name", $this->defaultPriority);
                $skipParseError = $this->io->confirm("Skip parsing errors");
                $skipZeroEstimate = $this->io->confirm("Skip tasks with zero estimated hours?");
                $existingTaskHandler = $this->io->choice("How to handle existing tasks found by Redmine ID:", [
                    self::HANDLER_SKIP,
                    self::HANDLER_UPDATE,
                    self::HANDLER_NEW
                ], self::HANDLER_SKIP);
                $outputFilePath = $this->io->ask("Output file path:", $filePath);
            }
        }

        $this->wbs->parser->throwOnError(!$skipParseError);
        $this->wbs->writer->setOutputFilePath($outputFilePath ?? $filePath);

        $this->logConfig('WBS upload process configuration', [
            "Project" => $project,
            "Spreadsheet" => $filePath,
            "Worksheet" => $worksheetName,
            "Tracker" => $tracker,
            "Status" => $status,
            "Priority" => $priority,
            "Skip Parsing errors" => $skipParseError ? "Yes" : "No",
            "Skip Zero Estimated Hours" => $skipZeroEstimate ? "Yes" : "No",
            "Existing Task Handler" => $existingTaskHandler,
            "Output file path" => $outputFilePath ?? 'Overrides input file'
        ]);

        $this->taskUploaderFacade->configure(
            columns: $this->wbs->columns,
            projectIdentifier: $project,
            trackerName: $tracker,
            statusName: $status,
            priorityName: $priority,
            existingTaskHandler: $existingTaskHandler,
            skipZeroEstimate: $skipZeroEstimate,
        );

        return $autoConfirm || $this->io->confirm('Do you want to continue?');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Uploads tasks from an Excel file to Redmine.')
            ->addArgument(self::ARG_SPREADSHEET, InputArgument::OPTIONAL, 'The path to the Excel spreadsheet.')
            ->addArgument(self::ARG_WORKSHEET, InputArgument::OPTIONAL, 'The WBS worksheet name.')
            ->addArgument(self::ARG_PROJECT, InputArgument::OPTIONAL, 'The Redmine project identifier.')
            ->addOption(self::OPT_TRACKER, 't', InputOption::VALUE_REQUIRED, 'The tracker name to use.', $this->defaultTracker)
            ->addOption(self::OPT_STATUS, 's', InputOption::VALUE_REQUIRED, 'The status name to use.', $this->defaultStatus)
            ->addOption(self::OPT_PRIORITY, 'p', InputOption::VALUE_REQUIRED, 'The priority name to use.', $this->defaultPriority)
            ->addOption(self::OPT_OUTPUT_FILE, 'of', InputOption::VALUE_OPTIONAL, 'Output file path. Overrides input file by default.', null)
            ->addOption(self::OPT_SKIP_PARSE_ERROR, 'spe', InputOption::VALUE_OPTIONAL, 'Skip parsing errors before uploading tasks.', true)
            ->addOption(self::OPT_SKIP_ZERO_ESTIMATE, 'sze', InputOption::VALUE_OPTIONAL, 'Skip uploading tasks with zero estimated hours.', true)
            ->addOption(self::OPT_EXISTING_TASK_HANDLER, 'eth', InputOption::VALUE_REQUIRED, sprintf('How to handle existing tasks found by Redmine ID: %s|%s|%s.', self::HANDLER_SKIP, self::HANDLER_UPDATE, self::HANDLER_NEW), self::HANDLER_SKIP);
    }
}
