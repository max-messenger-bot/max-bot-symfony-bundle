# Сервисы DI-контейнера

Бандл регистрирует следующие сервисы:

## Доступные сервисы

| Сервис / алиас          | Класс               | Описание                                     |
|-------------------------|---------------------|----------------------------------------------|
| `MaxBot::class`         | `MaxBot`            | Бот с зарегистрированными обработчиками      |
| `max_bot.bot`           | `MaxBot`            | Алиас для `MaxBot::class`                    |
| `MaxApiClient::class`   | `MaxApiClient`      | API-клиент для прямых запросов к Max API     |
| `max_bot.api_client`    | `MaxApiClient`      | Алиас для `MaxApiClient::class`              |
| `MaxApiConfig::class`   | `MaxApiConfig`      | Конфигурация API-клиента                     |
| `max_bot.api_config`    | `MaxApiConfig`      | Алиас для `MaxApiConfig::class`              |

## Внедрение через конструктор

### `MaxBot`

Основной объект бота с уже зарегистрированными обработчиками из `MaxBotHandlerInterface`.

```php
use MaxMessenger\Bot\MaxBot;

final class SomeService
{
    public function __construct(private readonly MaxBot $bot) {}

    public function process(string $json): void
    {
        $update = MaxBot::makeUpdateFromString($json);
        $this->bot->handleUpdate($update);
    }
}
```

### `MaxApiClient`

Клиент для прямых вызовов Max API (отправка сообщений, управление чатами и т.д.).

```php
use MaxMessenger\Bot\MaxApiClient;
use MaxMessenger\Bot\Model\Request\NewMessageBody;

final class NotificationService
{
    public function __construct(private readonly MaxApiClient $apiClient) {}

    public function sendAlert(int $chatId, string $text): void
    {
        $this->apiClient->sendMessageToChat($chatId, $text);
    }

    public function sendRichMessage(int $userId): void
    {
        $message = NewMessageBody::make('Выберите действие:');
        $message->addInlineKeyboard()
            ->addCallbackButton('Подтвердить', 'confirm')
            ->addCallbackButton('Отменить', 'cancel');

        $this->apiClient->sendMessageToUser($userId, $message);
    }
}
```

### `MaxApiConfig`

Конфигурация: access token, таймауты, base URL. Внедряйте, если нужно читать параметры
или передавать конфигурацию в кастомные компоненты.

```php
use MaxMessenger\Bot\MaxApiConfig;

final class DebugService
{
    public function __construct(private readonly MaxApiConfig $config) {}

    public function getBaseUrl(): string
    {
        return $this->config->getBaseUrl();
    }
}
```

## Использование в обработчиках

В обработчиках `MaxBotHandlerInterface` `MaxApiClient` доступен непосредственно через событие:

```php
public function register(MaxBot $bot): void
{
    $bot->onMessageCreated(function (MessageCreatedEvent $event): bool {
        // Через свойство события
        $event->apiClient->sendMessageToChat($event->getChatId(), 'Ответ');

        // Или через вспомогательные методы события
        $event->reply('Ответ', true);

        return true;
    });
}
```

## Использование `MaxApiClient` вне обработчиков

`MaxApiClient` можно внедрять в любые сервисы Symfony для отправки сообщений по расписанию,
уведомлений или администрирования чатов:

```php
// src/Scheduler/DailyDigestHandler.php

use MaxMessenger\Bot\MaxApiClient;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(frequency: '24 hours')]
final class DailyDigestHandler
{
    public function __construct(
        private readonly MaxApiClient $apiClient,
        private readonly DigestService $digest,
    ) {}

    public function __invoke(): void
    {
        $content = $this->digest->generate();

        foreach ($this->digest->getSubscriberIds() as $userId) {
            $this->apiClient->sendMessageToUser($userId, $content);
        }
    }
}
```

## Тег `max_bot.handler`

Этот тег определяет, какие сервисы будут зарегистрированы как обработчики событий в `MaxBot`.

### Автоматически (через `autoconfigure`)

Любой сервис, реализующий `MaxBotHandlerInterface`, получает тег автоматически.

### Вручную

```yaml
services:
    App\Bot\MyHandler:
        tags:
            - { name: max_bot.handler }
```

### С приоритетом

Обработчики с бо́льшим `priority` регистрируются раньше (вызываются первыми):

```yaml
services:
    App\Bot\AuthMiddleware:
        tags:
            - { name: max_bot.handler, priority: 100 }

    App\Bot\MessageHandler:
        tags:
            - { name: max_bot.handler, priority: 0 }
```

## Переопределение сервисов

### Замена `MaxApiConfig`

Если нужна нестандартная конфигурация, реализуйте `MaxApiConfigInterface`:

```php
use MaxMessenger\Bot\Contract\MaxApiConfigInterface;

final class MyConfig implements MaxApiConfigInterface
{
    // ... реализация интерфейса
}
```

Зарегистрируйте как алиас в `config/services.yaml`:

```yaml
services:
    MaxMessenger\Bot\MaxApiConfig:
        class: App\Bot\MyConfig
        # ... аргументы
```

### Замена HTTP-клиента

Передайте кастомный HTTP-клиент через декоратор или фабрику в `services.yaml`.
