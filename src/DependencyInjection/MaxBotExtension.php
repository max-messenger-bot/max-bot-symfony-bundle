<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\DependencyInjection;

use MaxMessenger\Bot\Bundle\Contract\MaxBotHandlerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

use function dirname;

final class MaxBotExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $webhookSecret = isset($config['webhook_secret']) ? (string)$config['webhook_secret'] : null;

        $container->setParameter('max_bot.access_token', (string)$config['access_token']);
        $container->setParameter('max_bot.webhook_secret', $webhookSecret);
        $container->setParameter('max_bot.base_url', (string)$config['base_url']);
        $container->setParameter('max_bot.connect_timeout', (int)$config['connect_timeout']);
        $container->setParameter('max_bot.timeout', (int)$config['timeout']);

        $container->registerForAutoconfiguration(MaxBotHandlerInterface::class)
            ->addTag('max_bot.handler');

        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/config'));
        $loader->load('services.php');
    }
}
