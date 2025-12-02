<?php

namespace App\TaskUploader\Command;

use App\TaskUploader\Parser\WbsParser;
use App\Common\ExcelParser\WorksheetTableParser;
use App\TaskUploader\Service\Exception\TrackerNotFoundException;
use App\TaskUploader\Service\RedmineService;
use App\TaskUploader\TaskUploaderFacade;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UploadTasksCommand extends Command
{
    protected static $defaultName = 'app:upload-tasks';

    public const string ARG_PROJECT = 'project';
    public const string ARG_FILEPATH = 'filepath';

    public function __construct(
        // private readonly RedmineService $redmineService,
        private readonly WbsParser $wbsParser,
        private readonly TaskUploaderFacade $taskUploaderFacade
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Uploads tasks from an Excel file to Redmine.')
            ->addArgument(self::ARG_FILEPATH, InputArgument::REQUIRED, 'The path to the Excel file.')
            ->addArgument(self::ARG_PROJECT, InputArgument::REQUIRED, 'The project identifier.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $project = $input->getArgument(self::ARG_PROJECT);
        $filePath = $input->getArgument(self::ARG_FILEPATH);
        $io->title("Starting WBS upload from: $filePath to redmine project: $project");

        try
        {
            $this->taskUploaderFacade->configure();
        }
        catch (TrackerNotFoundException)
        {

        }

        try
        {
            $this->wbsParser->open($filePath);
        }
        catch (InvalidArgumentException)
        {
            $io->error('Missing or invalid filepath');
            return Command::FAILURE;
        }
        catch (RuntimeException $e)
        {
            $io->error('An error occurred while opening the file: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $this->wbsParser->parse($output);

        $tasks = $this->wbsParser->getResults();


        return Command::SUCCESS;
    }
}
