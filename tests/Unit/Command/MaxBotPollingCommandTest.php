<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Tests\Unit\Command;

use Codeception\Test\Unit;
use MaxMessenger\Bot\Bundle\Command\MaxBotPollingCommand;
use MaxMessenger\Bot\MaxApiConfig;
use MaxMessenger\Bot\MaxBot;

use const SIGINT;
use const SIGTERM;

final class MaxBotPollingCommandTest extends Unit
{
    private MaxBotPollingCommand $command;

    protected function setUp(): void
    {
        $this->command = new MaxBotPollingCommand(new MaxBot(new MaxApiConfig('test-token')));
    }

    public function testCommandName(): void
    {
        self::assertSame('max-bot:run-polling', $this->command->getName());
    }

    public function testLimitOption(): void
    {
        $option = $this->command->getDefinition()->getOption('limit');
        self::assertSame(100, $option->getDefault());
    }

    public function testTimeoutOption(): void
    {
        $option = $this->command->getDefinition()->getOption('timeout');
        self::assertSame(60, $option->getDefault());
    }

    public function testSubscribedSignals(): void
    {
        $signals = $this->command->getSubscribedSignals();
        self::assertContains(SIGINT, $signals);
        self::assertContains(SIGTERM, $signals);
    }

    public function testHandleSignalReturnsFalse(): void
    {
        self::assertFalse($this->command->handleSignal(SIGINT));
    }
}
