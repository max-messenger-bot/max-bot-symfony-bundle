<?php

declare(strict_types=1);

use MaxMessenger\Bot\Bundle\Controller\MaxBotWebhookController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('max_bot_webhook', '/max-bot/webhook')
        ->controller([MaxBotWebhookController::class, 'webhook'])
        ->methods(['POST']);
};
