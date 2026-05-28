<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Tests\Unit\Command;

use Codeception\Test\Unit;
use MaxMessenger\Bot\Bundle\Command\MaxBotDeleteChatsCommand;
use MaxMessenger\Bot\MaxApiClient;
use MaxMessenger\Bot\MaxApiConfig;

final class MaxBotDeleteChatsCommandTest extends Unit
{
    private MaxBotDeleteChatsCommand $command;

    protected function setUp(): void
    {
        $this->command = new MaxBotDeleteChatsCommand(new MaxApiClient(new MaxApiConfig('test-token')));
    }

    public function testCommandName(): void
    {
        self::assertSame('max-bot:delete-chats', $this->command->getName());
    }

    public function testPerPageOption(): void
    {
        $option = $this->command->getDefinition()->getOption('per-page');
        self::assertSame(5, $option->getDefault());
    }
}
