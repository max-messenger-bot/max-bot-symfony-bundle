<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Tests\Unit\DependencyInjection;

use Codeception\Test\Unit;
use MaxMessenger\Bot\Bundle\Contract\MaxBotHandlerInterface;
use MaxMessenger\Bot\Bundle\DependencyInjection\MaxBotExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MaxBotExtensionTest extends Unit
{
    private MaxBotExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new MaxBotExtension();
        $this->container = new ContainerBuilder();
    }

    public function testLoadSetsParameters(): void
    {
        $this->extension->load(
            [['access_token' => 'test-token', 'webhook_secret' => 'test-secret']],
            $this->container
        );

        self::assertSame('test-token', $this->container->getParameter('max_bot.access_token'));
        self::assertSame('test-secret', $this->container->getParameter('max_bot.webhook_secret'));
        self::assertSame('https://platform-api.max.ru', $this->container->getParameter('max_bot.base_url'));
        self::assertSame(5000, $this->container->getParameter('max_bot.connect_timeout'));
        self::assertSame(10000, $this->container->getParameter('max_bot.timeout'));
    }

    public function testLoadSetsNullWhenWebhookSecretOmitted(): void
    {
        $this->extension->load(
            [['access_token' => 'test-token']],
            $this->container
        );

        self::assertNull($this->container->getParameter('max_bot.webhook_secret'));
    }

    public function testLoadRegistersHandlerInterfaceForAutoconfiguration(): void
    {
        $this->extension->load(
            [['access_token' => 'test-token']],
            $this->container
        );

        $autoconfigured = $this->container->getAutoconfiguredInstanceof();
        self::assertArrayHasKey(MaxBotHandlerInterface::class, $autoconfigured);
    }
}
