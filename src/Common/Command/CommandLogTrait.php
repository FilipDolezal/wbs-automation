<?php

namespace App\Common\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

trait CommandLogTrait
{
    private readonly LoggerInterface $logger;
    private SymfonyStyle $io;

    public function setupIO(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    public function logConfig(string $title, array $configuration): void
    {
        $title = trim($title);
        $this->io->title($title);
        $this->logger->info($title, $configuration);
        $this->ioTable($configuration);
    }

    public function ioTable(array $table): void
    {
        $rows = [];

        foreach ($table as $key => $value)
        {
            $rows[] = [$key, $value];
        }

        $this->io->table(['Property', 'Value'], $rows);
    }

    public function logException(Throwable $e, ?string $message = null): void
    {
        $this->io->error($message ?? $e->getMessage());
        $this->logger->error($message ?? $e->getMessage(), ['exception' => $e]);
    }

    public function logError(string $message): void
    {
        $message = trim($message);
        $this->io->error($message);
        $this->logger->error($message);
    }

    public function logInfo(string $message): void
    {
        $message = trim($message);
        $this->io->info($message);
        $this->logger->info($message);
    }
}