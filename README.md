# Max Messenger Bot — Symfony Bundle

Symfony-бандл для интеграции [Max Messenger Bot API PHP SDK](https://github.com/max-messenger-bot/max-bot-api-php)
в Symfony-приложения.

Бандл регистрирует готовые сервисы в DI-контейнере, предоставляет webhook-контроллер, команду
для запуска long polling и набор управляющих команд для работы с ботом из консоли.

## Требования

- PHP 8.2+
- Symfony 6.4 или 7.x

## Установка

```bash
composer require max-messenger-bot/symfony-bundle
```

Зарегистрируйте бандл в `config/bundles.php`:

```php
return [
    // ...
    MaxMessenger\Bot\Bundle\MaxBotBundle::class => ['all' => true],
];
```

Создайте файл конфигурации `config/packages/max_bot.yaml`:

```yaml
max_bot:
    access_token: '%env(MAX_BOT_TOKEN)%'
    webhook_secret: '%env(MAX_BOT_SECRET)%'
```

Добавьте переменные окружения в `.env`:

```dotenv
MAX_BOT_TOKEN=your-access-token
MAX_BOT_SECRET=your-webhook-secret
```

## Быстрый старт

### 1. Создайте обработчик событий

```php
// src/Bot/GreetingHandler.php

use MaxMessenger\Bot\Bundle\Contract\MaxBotHandlerInterface;
use MaxMessenger\Bot\MaxBot;
use MaxMessenger\Bot\MaxBot\Event\BotStartedEvent;
use MaxMessenger\Bot\MaxBot\Event\MessageCreatedEvent;

final class GreetingHandler implements MaxBotHandlerInterface
{
    public function register(MaxBot $bot): void
    {
        $bot->onBotStarted(function (BotStartedEvent $event): bool {
            $event->sendToChat('Привет! Я бот на Symfony.');
            return true;
        });

        $bot->onMessageCreated(function (MessageCreatedEvent $event): bool {
            $event->reply('Ваше сообщение получено.', true);
            return true;
        });
    }
}
```

Благодаря `autoconfigure`, сервис подхватывается автоматически — никакой ручной регистрации не нужно.

### 2. Выберите режим работы

**Webhook** — добавьте маршрут в `config/routes.yaml`:

```yaml
max_bot:
    resource: '@MaxBotBundle/config/routes.php'
    type: php
```

**Long polling** — запустите команду:

```bash
php bin/console max-bot:run-polling
```

## Консольные команды

| Команда | Описание |
|---|---|
| `max-bot:run-polling` | Запуск бота в режиме long polling |
| `max-bot:debug` | Long polling с выводом сырых событий (для отладки) |
| `max-bot:chats` | Список чатов бота с пагинацией |
| `max-bot:delete-chats` | Удаление чатов, где бот является владельцем |
| `max-bot:subscribe` | Регистрация webhook-подписки |
| `max-bot:subscriptions` | Просмотр активных webhook-подписок |
| `max-bot:unsubscribe` | Удаление webhook-подписки |

Все команды берут access token из конфигурации Symfony — запрашивать его вручную не нужно.

## Документация

- [Установка и настройка](docs/01-Installation.md)
- [Конфигурация](docs/02-Configuration.md)
- [Обработчики событий](docs/03-Handlers.md)
- [Webhook](docs/04-Webhook.md)
- [Long Polling](docs/05-LongPolling.md)
- [Сервисы DI-контейнера](docs/06-Services.md)
- [Консольные команды](docs/08-Commands.md)
- [Примеры](docs/07-Examples.md)

## Документация основного SDK

Подробнее о работе с событиями, сообщениями и API-клиентом читайте в документации
[max-messenger-bot/max-bot-api-php](https://github.com/max-messenger-bot/max-bot-api-php).
