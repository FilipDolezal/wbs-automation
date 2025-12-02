<?php

namespace App\TaskUploader\Command;

use App\Common\ExcelParser\WbsParser\WbsParser;
use App\Common\ExcelParser\WorksheetTableParser;
use App\TaskUploader\Service\RedmineService;
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

    public function __construct(
        // private readonly RedmineService $redmineService,
        private readonly WbsParser $wbsParser
    )
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Uploads tasks from an Excel file to Redmine.')
            ->addArgument('filePath', InputArgument::REQUIRED, 'The path to the Excel file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try
        {
            $filePath = $input->getArgument('filePath');
            $io->title('Parsing tasks from: ' . $filePath);
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

        // todo: decide if you turn excelParser into generator or not... continue from here
        try
        {
            $this->wbsParser->parse();
            $this->wbsParser->test();

            // In the next step, we will upload these tasks to Redmine.
            // foreach ($tasks as $task) {
            //     $this->redmineService->uploadTask($task);
            // }

        }
        catch (\Exception $e)
        {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
