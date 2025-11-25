<?php

namespace App\TaskUploader\Command;

use App\Common\ExcelParser\WorksheetTableParser\WorksheetTableParser;
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
        private readonly RedmineService $redmineService,
        private readonly WorksheetTableParser $excelParser
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
            $this->excelParser->open($filePath);
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

        try
        {
            $tasks = $this->excelParser->parse();
            $io->text(count($tasks) . ' tasks found.');

            if (empty($tasks))
            {
                $io->warning('No tasks found in the file.');
                return Command::SUCCESS;
            }

            $io->section('Parsed Tasks (for debugging):');
            foreach ($tasks as $task)
            {
                $io->writeln(sprintf(
                    'Subject: <info>%s</info> | Estimated Hours: <info>%s</info>',
                    $task['subject'],
                    $task['estimated_hours'] ?? 'N/A'
                ));
            }

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
