<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Tests\Unit\Command;

use Codeception\Test\Unit;
use MaxMessenger\Bot\Bundle\Command\MaxBotSubscribeCommand;
use MaxMessenger\Bot\MaxApiClient;
use MaxMessenger\Bot\MaxApiConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class MaxBotSubscribeCommandTest extends Unit
{
    private MaxApiClient $apiClient;

    protected function setUp(): void
    {
        $this->apiClient = new MaxApiClient(new MaxApiConfig('test-token'));
    }

    public function testCommandName(): void
    {
        self::assertSame('max-bot:subscribe', $this->makeCommand()->getName());
    }

    public function testUrlArgumentIsOptional(): void
    {
        $arg = $this->makeCommand()->getDefinition()->getArgument('url');
        self::assertFalse($arg->isRequired());
    }

    public function testSecretOption(): void
    {
        self::assertTrue($this->makeCommand()->getDefinition()->hasOption('secret'));
    }

    public function testTypesOptionDefaultsToAll(): void
    {
        self::assertSame('all', $this->makeCommand()->getDefinition()->getOption('types')->getDefault());
    }

    public function testFailsOnNonHttpsUrl(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $tester->execute(['url' => 'http://example.com/webhook']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testFailsOnUrlWithPort(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $tester->execute(['url' => 'https://example.com:8080/webhook']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testFailsOnUnknownEventType(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $tester->execute([
            'url'      => 'https://example.com/webhook',
            '--secret' => 'valid-secret-key',
            '--types'  => 'unknown_event_type',
        ]);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testRouterUrlIsUsedAsDefault(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())
            ->method('generate')
            ->with('max_bot_webhook', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/max-bot/webhook');

        $tester = new CommandTester($this->makeCommand($router));
        // No URL argument: accept suggested URL (empty = use default), provide secret, cancel confirm
        $tester->setInputs(['', 'valid-secret', 'no']);
        $tester->execute([]);

        self::assertStringContainsString('https://example.com/max-bot/webhook', $tester->getDisplay());
    }

    public function testRouterFallsBackWhenRouteNotFound(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')
            ->willThrowException(new RouteNotFoundException());

        $tester = new CommandTester($this->makeCommand($router));
        // No URL argument: user provides it manually; router suggestion should NOT appear
        $tester->setInputs(['https://example.com/webhook', 'valid-secret', 'no']);
        $tester->execute([]);

        self::assertStringNotContainsString('Обнаружен маршрут', $tester->getDisplay());
    }

    public function testWorksWithoutRouter(): void
    {
        $command = $this->makeCommand(null);
        self::assertSame('max-bot:subscribe', $command->getName());
    }

    private function makeCommand(?RouterInterface $router = null): MaxBotSubscribeCommand
    {
        return new MaxBotSubscribeCommand($this->apiClient, $router);
    }
}
