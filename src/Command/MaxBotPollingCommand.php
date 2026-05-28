<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Command;

use MaxMessenger\Bot\MaxBot;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use const SIGINT;
use const SIGTERM;

#[AsCommand(
    name: 'max-bot:run-polling',
    description: 'Run Max Bot in long polling mode',
)]
final class MaxBotPollingCommand extends Command
{
    private bool $shouldStop = false;

    public function __construct(private readonly MaxBot $bot)
    {
        parent::__construct();
    }

    public function getSubscribedSignals(): array
    {
        return [SIGINT, SIGTERM];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->shouldStop = true;

        return false;
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max updates per request (1–1000)', 100)
            ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Long polling timeout in seconds (0–90)', 60);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(1, min(1000, (int)$input->getOption('limit')));
        $timeout = max(0, min(90, (int)$input->getOption('timeout')));
        $marker = null;

        $io->info('Max Bot long polling started. Press Ctrl+C to stop.');

        while (!$this->shouldStop) {
            try {
                $marker = $this->bot->handleFromServer($limit, $timeout, $marker);
            } catch (Throwable $e) {
                $io->error($e->getMessage());
                sleep(5);
            }
        }

        $io->info('Stopped.');

        return Command::SUCCESS;
    }
}
