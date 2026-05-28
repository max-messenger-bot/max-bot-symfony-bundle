<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Tests\Unit\Command;

use Codeception\Test\Unit;
use MaxMessenger\Bot\Bundle\Command\MaxBotDebugCommand;
use MaxMessenger\Bot\MaxApiClient;
use MaxMessenger\Bot\MaxApiConfig;

use const SIGINT;
use const SIGTERM;

final class MaxBotDebugCommandTest extends Unit
{
    private MaxBotDebugCommand $command;

    protected function setUp(): void
    {
        $this->command = new MaxBotDebugCommand(new MaxApiClient(new MaxApiConfig('test-token')));
    }

    public function testCommandName(): void
    {
        self::assertSame('max-bot:debug', $this->command->getName());
    }

    public function testLimitOption(): void
    {
        $option = $this->command->getDefinition()->getOption('limit');
        self::assertSame(1, $option->getDefault());
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
