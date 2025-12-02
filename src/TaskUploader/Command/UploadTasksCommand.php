<?php

namespace App\TaskUploader\Command;

use App\TaskUploader\Parser\WbsParser;
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

        $this->wbsParser->parse($output);

        $tasks = $this->wbsParser->getResults();
        $parents = array_unique(array_values(array_map(static fn ($t) => $t->parent, $tasks)));


        return Command::SUCCESS;
    }
}
