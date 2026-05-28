<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Tests\Unit\Factory;

use Codeception\Test\Unit;
use MaxMessenger\Bot\Bundle\Contract\MaxBotHandlerInterface;
use MaxMessenger\Bot\Bundle\Factory\MaxBotFactory;
use MaxMessenger\Bot\MaxApiClient;
use MaxMessenger\Bot\MaxApiConfig;
use MaxMessenger\Bot\MaxBot;

final class MaxBotFactoryTest extends Unit
{
    private MaxApiClient $apiClient;

    protected function setUp(): void
    {
        $this->apiClient = new MaxApiClient(new MaxApiConfig('test-token'));
    }

    public function testCreateReturnsMaxBot(): void
    {
        $factory = new MaxBotFactory($this->apiClient, null, []);

        self::assertInstanceOf(MaxBot::class, $factory->create());
    }

    public function testApiClientIsShared(): void
    {
        $factory = new MaxBotFactory($this->apiClient, null, []);

        self::assertSame($this->apiClient, $factory->create()->apiClient);
    }

    public function testCreateCallsRegisterOnEachHandler(): void
    {
        $calls = 0;
        $makeHandler = function () use (&$calls): MaxBotHandlerInterface {
            return new class ($calls) implements MaxBotHandlerInterface {
                public function __construct(private int &$calls)
                {
                }

                public function register(MaxBot $bot): void
                {
                    $this->calls++;
                }
            };
        };

        $factory = new MaxBotFactory($this->apiClient, null, [$makeHandler(), $makeHandler(), $makeHandler()]);
        $factory->create();

        self::assertSame(3, $calls);
    }

    public function testEmptyWebhookSecretIsConvertedToNull(): void
    {
        $factory = new MaxBotFactory($this->apiClient, '', []);

        self::assertInstanceOf(MaxBot::class, $factory->create());
    }
}
