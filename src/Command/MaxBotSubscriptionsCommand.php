<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Command;

use MaxMessenger\Bot\Exception\SimpleQueryError;
use MaxMessenger\Bot\MaxApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function count;
use function sprintf;

#[AsCommand(
    name: 'max-bot:subscriptions',
    description: 'List active webhook subscriptions',
)]
final class MaxBotSubscriptionsCommand extends Command
{
    public function __construct(private readonly MaxApiClient $apiClient)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Webhook-подписки');

        try {
            $subscriptions = $this->apiClient->getSubscriptions()->getSubscriptions();
        } catch (SimpleQueryError $e) {
            $io->error(sprintf('Ошибка API: %s', $e->getMessage()));
            return Command::FAILURE;
        } catch (Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if (empty($subscriptions)) {
            $io->info('Подписки не найдены.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($subscriptions as $i => $subscription) {
            $updateTypes = $subscription->getUpdateTypes();
            $types = $updateTypes === null || $updateTypes === []
                ? 'все'
                : implode(', ', array_map(static fn($t): string => $t->value, $updateTypes));

            $rows[] = [
                $i + 1,
                $subscription->getUrl(),
                $subscription->getTime()->format('Y-m-d H:i:s'),
                $subscription->getVersion() ?? '—',
                $types,
            ];
        }

        $io->table(['#', 'URL', 'Дата создания', 'Версия API', 'Типы событий'], $rows);
        $io->success(sprintf('Найдено подписок: %d', count($subscriptions)));

        return Command::SUCCESS;
    }
}
