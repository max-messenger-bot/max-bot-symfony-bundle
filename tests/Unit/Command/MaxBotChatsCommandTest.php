<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Tests\Unit\Command;

use Codeception\Test\Unit;
use MaxMessenger\Bot\Bundle\Command\MaxBotChatsCommand;
use MaxMessenger\Bot\MaxApiClient;
use MaxMessenger\Bot\MaxApiConfig;

final class MaxBotChatsCommandTest extends Unit
{
    private MaxBotChatsCommand $command;

    protected function setUp(): void
    {
        $this->command = new MaxBotChatsCommand(new MaxApiClient(new MaxApiConfig('test-token')));
    }

    public function testCommandName(): void
    {
        self::assertSame('max-bot:chats', $this->command->getName());
    }

    public function testPerPageOption(): void
    {
        $option = $this->command->getDefinition()->getOption('per-page');
        self::assertSame(5, $option->getDefault());
    }
}
