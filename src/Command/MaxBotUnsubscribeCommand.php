<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Command;

use MaxMessenger\Bot\Exception\SimpleQueryError;
use MaxMessenger\Bot\MaxApiClient;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function count;
use function sprintf;

#[AsCommand(
    name: 'max-bot:unsubscribe',
    description: 'Remove a webhook subscription',
)]
final class MaxBotUnsubscribeCommand extends Command
{
    public function __construct(private readonly MaxApiClient $apiClient)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Удаление Webhook-подписки');

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
                $types,
            ];
        }

        $io->table(['#', 'URL', 'Дата создания', 'Типы событий'], $rows);

        $selectedNumber = (int)$io->ask(
            sprintf('Введите номер подписки для удаления (1–%d) или 0 для отмены', count($subscriptions)),
            '0',
            function (string $value): string {
                if (!preg_match('/^\d+$/', $value)) {
                    throw new RuntimeException('Введите число.');
                }
                return $value;
            }
        );

        if ($selectedNumber === 0) {
            $io->text('Отменено.');
            return Command::SUCCESS;
        }

        $selectedIndex = $selectedNumber - 1;
        if (!isset($subscriptions[$selectedIndex])) {
            $io->error('Неверный номер подписки.');
            return Command::FAILURE;
        }

        $url = $subscriptions[$selectedIndex]->getUrl();

        if (!$io->confirm(sprintf('Удалить подписку "%s"?', $url), false)) {
            $io->text('Отменено.');
            return Command::SUCCESS;
        }

        try {
            $this->apiClient->unsubscribe($url);
        } catch (SimpleQueryError $e) {
            $io->error(sprintf('Ошибка API: %s', $e->getMessage()));
            return Command::FAILURE;
        } catch (Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('Подписка "%s" удалена.', $url));
        return Command::SUCCESS;
    }
}
