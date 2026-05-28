<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use MaxMessenger\Bot\Bundle\Command\MaxBotChatsCommand;
use MaxMessenger\Bot\Bundle\Command\MaxBotDebugCommand;
use MaxMessenger\Bot\Bundle\Command\MaxBotDeleteChatsCommand;
use MaxMessenger\Bot\Bundle\Command\MaxBotPollingCommand;
use MaxMessenger\Bot\Bundle\Command\MaxBotSubscribeCommand;
use MaxMessenger\Bot\Bundle\Command\MaxBotSubscriptionsCommand;
use MaxMessenger\Bot\Bundle\Command\MaxBotUnsubscribeCommand;
use MaxMessenger\Bot\Bundle\Controller\MaxBotWebhookController;
use MaxMessenger\Bot\Bundle\Factory\MaxBotFactory;
use MaxMessenger\Bot\MaxApiClient;
use MaxMessenger\Bot\MaxApiConfig;
use MaxMessenger\Bot\MaxBot;
use Symfony\Component\Routing\RouterInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('max_bot.api_config', MaxApiConfig::class)
        ->args([
            param('max_bot.access_token'),
            null,
            param('max_bot.base_url'),
        ])
        ->call('setConnectTimeout', [param('max_bot.connect_timeout')])
        ->call('setTimeout', [param('max_bot.timeout')]);

    $services->alias(MaxApiConfig::class, 'max_bot.api_config')
        ->public();

    $services->set('max_bot.api_client', MaxApiClient::class)
        ->args([service('max_bot.api_config')]);

    $services->alias(MaxApiClient::class, 'max_bot.api_client')
        ->public();

    $services->set('max_bot.factory', MaxBotFactory::class)
        ->args([
            service('max_bot.api_client'),
            param('max_bot.webhook_secret'),
            tagged_iterator('max_bot.handler'),
        ]);

    $services->set(MaxBot::class)
        ->factory([service('max_bot.factory'), 'create'])
        ->public();

    $services->alias('max_bot.bot', MaxBot::class);

    $services->set(MaxBotWebhookController::class)
        ->args([service(MaxBot::class)])
        ->tag('controller.service_arguments')
        ->public();

    $services->set(MaxBotPollingCommand::class)
        ->args([service(MaxBot::class)])
        ->tag('console.command');

    $services->set(MaxBotDebugCommand::class)
        ->args([service(MaxApiClient::class)])
        ->tag('console.command');

    $services->set(MaxBotChatsCommand::class)
        ->args([service(MaxApiClient::class)])
        ->tag('console.command');

    $services->set(MaxBotDeleteChatsCommand::class)
        ->args([service(MaxApiClient::class)])
        ->tag('console.command');

    $services->set(MaxBotSubscribeCommand::class)
        ->args([
            service(MaxApiClient::class),
            service(RouterInterface::class)->nullOnInvalid(),
        ])
        ->tag('console.command');

    $services->set(MaxBotSubscriptionsCommand::class)
        ->args([service(MaxApiClient::class)])
        ->tag('console.command');

    $services->set(MaxBotUnsubscribeCommand::class)
        ->args([service(MaxApiClient::class)])
        ->tag('console.command');
};
