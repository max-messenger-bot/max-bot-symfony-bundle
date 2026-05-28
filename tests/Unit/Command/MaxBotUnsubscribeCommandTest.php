<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Tests\Unit\Command;

use Codeception\Test\Unit;
use MaxMessenger\Bot\Bundle\Command\MaxBotUnsubscribeCommand;
use MaxMessenger\Bot\MaxApiClient;
use MaxMessenger\Bot\MaxApiConfig;

final class MaxBotUnsubscribeCommandTest extends Unit
{
    private MaxBotUnsubscribeCommand $command;

    protected function setUp(): void
    {
        $this->command = new MaxBotUnsubscribeCommand(new MaxApiClient(new MaxApiConfig('test-token')));
    }

    public function testCommandName(): void
    {
        self::assertSame('max-bot:unsubscribe', $this->command->getName());
    }

    public function testHasNoArguments(): void
    {
        self::assertEmpty($this->command->getDefinition()->getArguments());
    }

    public function testHasNoOptions(): void
    {
        self::assertEmpty($this->command->getDefinition()->getOptions());
    }
}
