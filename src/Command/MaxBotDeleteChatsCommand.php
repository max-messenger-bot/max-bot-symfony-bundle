<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Command;

use MaxMessenger\Bot\Exception\SimpleQueryError;
use MaxMessenger\Bot\MaxApiClient;
use MaxMessenger\Bot\Model\Response\Chat;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function sprintf;

#[AsCommand(
    name: 'max-bot:delete-chats',
    description: 'List and delete chats owned by the bot',
)]
final class MaxBotDeleteChatsCommand extends Command
{
    public function __construct(private readonly MaxApiClient $apiClient)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('per-page', null, InputOption::VALUE_REQUIRED, 'Chats per page (1–50)', 5);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $perPage = max(1, min(50, (int)$input->getOption('per-page')));

        try {
            $botInfo = $this->apiClient->getMyInfo();
        } catch (Throwable $e) {
            $io->error(sprintf('Ошибка получения информации о боте: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $io->title(
            sprintf(
                'Удаление чатов бота %s (ID: %d)',
                $botInfo->getFirstName(),
                $botInfo->getUserId()
            )
        );

        $marker = null;
        $pageNumber = 1;

        do {
            try {
                $result = $this->apiClient->getChats($perPage, $marker);
            } catch (SimpleQueryError $e) {
                $io->error(sprintf('Ошибка API: %s', $e->getMessage()));
                return Command::FAILURE;
            } catch (Throwable $e) {
                $io->error($e->getMessage());
                return Command::FAILURE;
            }

            $chats = $result->getChats();
            $marker = $result->getMarker();

            $ownerChats = array_values(
                array_filter(
                    $chats,
                    static fn(Chat $c): bool => $c->getOwnerId() === $botInfo->getUserId()
                )
            );

            $io->section(sprintf('Страница %d', $pageNumber));

            if (empty($ownerChats)) {
                $io->text('Чатов, где бот владелец, на этой странице нет.');
            } else {
                $this->processPage($io, $ownerChats);
            }

            if ($marker !== null) {
                if (!$io->confirm('Перейти к следующей странице?')) {
                    break;
                }
                $pageNumber++;
            }
        } while ($marker !== null);

        $io->success('Готово.');
        return Command::SUCCESS;
    }

    /**
     * @param list<Chat> $ownerChats
     * @return list<Chat>
     */
    private function deleteChat(SymfonyStyle $io, array $ownerChats, int $index): array
    {
        if (!isset($ownerChats[$index])) {
            return $ownerChats;
        }

        $chat = $ownerChats[$index];
        $title = $chat->getTitle() ?? sprintf('ID: %d', $chat->getChatId());

        if (!$io->confirm(sprintf('Безвозвратно удалить чат "%s"?', $title), false)) {
            $io->text('Отменено.');
            return $ownerChats;
        }

        try {
            $this->apiClient->deleteChat($chat->getChatId());
        } catch (SimpleQueryError $e) {
            $io->error(sprintf('Ошибка API: %s', $e->getMessage()));
            return $ownerChats;
        } catch (Throwable $e) {
            $io->error($e->getMessage());
            return $ownerChats;
        }

        $io->text(sprintf('✓ Чат "%s" удалён.', $title));

        return array_values(
            array_filter(
                $ownerChats,
                static fn(Chat $c): bool => $c->getChatId() !== $chat->getChatId()
            )
        );
    }

    /**
     * @param list<Chat> $ownerChats
     */
    private function processPage(SymfonyStyle $io, array $ownerChats): void
    {
        while (!empty($ownerChats)) {
            $rows = [];
            foreach ($ownerChats as $i => $chat) {
                $rows[] = [
                    $i + 1,
                    $chat->getChatId(),
                    $chat->getTitle() ?? '—',
                    $chat->getType()?->value ?? $chat->getTypeRaw(),
                    $chat->getParticipantsCount(),
                ];
            }
            $io->table(['#', 'ID', 'Название', 'Тип', 'Участников'], $rows);

            $input = (string)$io->ask(
                'Номер чата для удаления, "all" для удаления всех на странице, или Enter для пропуска',
            );

            if ($input === '') {
                break;
            }

            if (strtolower($input) === 'all') {
                foreach (array_keys($ownerChats) as $index) {
                    $ownerChats = $this->deleteChat($io, $ownerChats, $index);
                }
                break;
            }

            if (!preg_match('/^\d+$/', $input)) {
                $io->error('Введите номер чата или "all".');
                continue;
            }

            $selectedIndex = (int)$input - 1;
            if (!isset($ownerChats[$selectedIndex])) {
                $io->error('Неверный номер чата.');
                continue;
            }

            $ownerChats = $this->deleteChat($io, $ownerChats, $selectedIndex);
        }
    }
}
