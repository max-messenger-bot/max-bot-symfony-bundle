<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Tests\Unit\DependencyInjection;

use Codeception\Test\Unit;
use MaxMessenger\Bot\Bundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends Unit
{
    private Processor $processor;
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->processor = new Processor();
        $this->configuration = new Configuration();
    }

    public function testDefaultValues(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [['access_token' => 'test-token']]
        );

        self::assertNull($config['webhook_secret']);
        self::assertSame('https://platform-api.max.ru', $config['base_url']);
        self::assertSame(5000, $config['connect_timeout']);
        self::assertSame(10000, $config['timeout']);
    }

    public function testAccessTokenIsRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [[]]);
    }

    public function testAccessTokenCannotBeEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processor->processConfiguration(
            $this->configuration,
            [['access_token' => '']]
        );
    }

    public function testCustomValues(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [[
                'access_token'    => 'my-token',
                'webhook_secret'  => 'my-secret',
                'base_url'        => 'https://custom.api.example.com',
                'connect_timeout' => 3000,
                'timeout'         => 7000,
            ]]
        );

        self::assertSame('my-token', $config['access_token']);
        self::assertSame('my-secret', $config['webhook_secret']);
        self::assertSame('https://custom.api.example.com', $config['base_url']);
        self::assertSame(3000, $config['connect_timeout']);
        self::assertSame(7000, $config['timeout']);
    }

    public function testWebhookSecretCanBeExplicitlyNull(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [['access_token' => 'test-token', 'webhook_secret' => null]]
        );

        self::assertNull($config['webhook_secret']);
    }
}
