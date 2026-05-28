<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Command;

use MaxMessenger\Bot\Exception\SimpleQueryError;
use MaxMessenger\Bot\MaxApiClient;
use MaxMessenger\Bot\Model\Response\Update;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function count;
use function sprintf;

use const SIGINT;
use const SIGTERM;

#[AsCommand(
    name: 'max-bot:debug',
    description: 'Print incoming events via long polling (for debugging)',
)]
final class MaxBotDebugCommand extends Command
{
    private bool $shouldStop = false;

    public function __construct(private readonly MaxApiClient $apiClient)
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
            ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Long polling timeout in seconds (0–90)', 60)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max events per request (1–1000)', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timeout = max(0, min(90, (int)$input->getOption('timeout')));
        $limit = max(1, min(1000, (int)$input->getOption('limit')));

        try {
            $subscriptions = $this->apiClient->getSubscriptions()->getSubscriptions();
        } catch (Throwable $e) {
            $io->error(sprintf('Ошибка получения подписок: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        if (!empty($subscriptions)) {
            $io->warning([
                'Long Polling невозможен при наличии активных webhook-подписок.',
                sprintf('Найдено подписок: %d', count($subscriptions)),
                'Используйте max-bot:unsubscribe для удаления.',
            ]);
            return Command::FAILURE;
        }

        $io->info('Long Polling доступен. Нажмите Ctrl+C для остановки.');

        $marker = null;
        $count = 0;

        while (!$this->shouldStop) {
            try {
                $io->text(sprintf('[%s] Ожидание событий...', date('Y-m-d H:i:s')));

                $response = $this->apiClient->getUpdates(limit: $limit, timeout: $timeout, marker: $marker);
                $updates = $response->getUpdates();
                $marker = $response->getMarker();

                if (empty($updates)) {
                    $io->text(sprintf('[%s] Событий нет', date('Y-m-d H:i:s')));
                    $output->writeln('');
                    continue;
                }

                foreach ($updates as $update) {
                    $count++;
                    $this->printUpdate($io, $output, $update, $count);
                }
            } catch (SimpleQueryError $e) {
                $io->error(sprintf('Ошибка API: %s', $e->getMessage()));
                sleep(5);
            } catch (Throwable $e) {
                $io->error($e->getMessage());
                sleep(5);
            }
        }

        $io->info('Остановлено.');
        return Command::SUCCESS;
    }

    private function printUpdate(SymfonyStyle $io, OutputInterface $output, Update $update, int $number): void
    {
        $io->section(sprintf('Событие #%d', $number));

        $updateType = $update->getUpdateType();
        $io->definitionList(
            ['Тип' => sprintf('%s (%s)', $updateType?->value ?? 'unknown', $update->getUpdateTypeRaw())],
            ['Время' => $update->getTimestamp()->format('Y-m-d H:i:s')],
        );

        $json = json_encode($update->getRawData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $output->writeln((string)$json);
        $output->writeln('');
    }
}
