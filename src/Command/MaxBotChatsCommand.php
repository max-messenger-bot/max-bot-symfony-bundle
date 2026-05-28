<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Command;

use MaxMessenger\Bot\Exception\SimpleQueryError;
use MaxMessenger\Bot\MaxApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function count;
use function sprintf;

#[AsCommand(
    name: 'max-bot:chats',
    description: 'List bot chats with pagination',
)]
final class MaxBotChatsCommand extends Command
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

        $io->title(sprintf('Чаты бота %s (ID: %d)', $botInfo->getFirstName(), $botInfo->getUserId()));

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

            if (empty($chats)) {
                $io->info('Чаты не найдены.');
                break;
            }

            $io->section(sprintf('Страница %d (%d чат(ов))', $pageNumber, count($chats)));

            $rows = [];
            foreach ($chats as $chat) {
                $isOwner = $chat->getOwnerId() === $botInfo->getUserId();
                $rows[] = [
                    $chat->getChatId(),
                    $chat->getTitle() ?? '—',
                    $chat->getType()?->value ?? $chat->getTypeRaw(),
                    $chat->getStatus()?->value ?? $chat->getStatusRaw(),
                    $chat->getParticipantsCount(),
                    $isOwner ? 'да' : 'нет',
                    $chat->getLastEventTime()->format('Y-m-d H:i:s'),
                ];
            }

            $io->table(
                ['ID', 'Название', 'Тип', 'Статус', 'Участников', 'Владелец', 'Последнее событие'],
                $rows
            );

            if ($marker !== null) {
                if (!$io->confirm('Загрузить следующую страницу?')) {
                    break;
                }
                $pageNumber++;
            }
        } while ($marker !== null);

        $io->success('Готово.');
        return Command::SUCCESS;
    }
}
