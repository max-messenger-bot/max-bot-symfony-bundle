# Обработчики событий

## Концепция

Бандл использует паттерн «регистратор обработчиков» через интерфейс `MaxBotHandlerInterface`.
Каждый обработчик — это сервис Symfony, который при инициализации получает объект `MaxBot`
и регистрирует в нём нужные closures через методы `on*()`.

Благодаря механизму `autoconfigure` в Symfony, любой сервис, реализующий `MaxBotHandlerInterface`,
автоматически получает тег `max_bot.handler` и регистрируется при создании бота.

## Создание обработчика

### Минимальный пример

```php
// src/Bot/MyHandler.php

namespace App\Bot;

use MaxMessenger\Bot\Bundle\Contract\MaxBotHandlerInterface;
use MaxMessenger\Bot\MaxBot;
use MaxMessenger\Bot\MaxBot\Event\MessageCreatedEvent;

final class MyHandler implements MaxBotHandlerInterface
{
    public function register(MaxBot $bot): void
    {
        $bot->onMessageCreated(function (MessageCreatedEvent $event): bool {
            $event->reply('Привет!', true);
            return true;
        });
    }
}
```

Если в вашем проекте включён `autoconfigure` (по умолчанию — да), никакой дополнительной
конфигурации не требуется. Symfony автоматически найдёт этот сервис и зарегистрирует его.

### С зависимостями

Обработчики — полноценные Symfony-сервисы и поддерживают инъекцию зависимостей:

```php
namespace App\Bot;

use App\Repository\UserRepository;
use MaxMessenger\Bot\Bundle\Contract\MaxBotHandlerInterface;
use MaxMessenger\Bot\MaxBot;
use MaxMessenger\Bot\MaxBot\Event\BotStartedEvent;
use MaxMessenger\Bot\MaxBot\Event\MessageCreatedEvent;
use Psr\Log\LoggerInterface;

final class UserHandler implements MaxBotHandlerInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function register(MaxBot $bot): void
    {
        $bot->onBotStarted(function (BotStartedEvent $event): bool {
            $userId = $event->getUserId();
            $user = $this->userRepository->findOrCreate($userId);
            $this->logger->info('Bot started', ['userId' => $userId]);

            $event->sendToChat("Привет, {$user->getName()}!");
            return true;
        });

        $bot->onMessageCreated(function (MessageCreatedEvent $event): bool {
            $this->logger->info('Message received', [
                'chatId' => $event->getChatId(),
                'text'   => $event->getMessage()->getText(),
            ]);
            return false; // Передать дальше другим обработчикам
        });
    }
}
```

## Регистрация нескольких типов событий

Один обработчик может регистрировать любое количество событий:

```php
public function register(MaxBot $bot): void
{
    $bot->onBotStarted(fn(BotStartedEvent $e) => $this->onStart($e));
    $bot->onBotStopped(fn(BotStoppedEvent $e) => $this->onStop($e));
    $bot->onBotAddedToChat(fn(BotAddedToChatEvent $e) => $this->onAdded($e));
    $bot->onBotRemovedFromChat(fn(BotRemovedFromChatEvent $e) => $this->onRemoved($e));
}
```

## Разделение по ответственности

Рекомендуется разбивать обработчики по логическим группам:

```
src/
  Bot/
    StartHandler.php        # обработка /start и bot_started
    MessageHandler.php      # входящие сообщения
    CommandHandler.php      # команды
    CallbackHandler.php     # нажатия кнопок
```

## Типы событий

Полный список типов событий и их методов описан в документации основного SDK:
[Обработка событий бота](https://github.com/max-messenger-bot/max-bot-api-php/blob/main/docs/ProcessingEvents.md).

| Метод `MaxBot`          | Тип события                | Описание                              |
|-------------------------|----------------------------|---------------------------------------|
| `onMessageCreated`      | `MessageCreatedEvent`      | Новое сообщение в чате                |
| `onMessageEdited`       | `MessageEditedEvent`       | Сообщение отредактировано             |
| `onMessageRemoved`      | `MessageRemovedEvent`      | Сообщение удалено                     |
| `onMessageCallback`     | `MessageCallbackEvent`     | Пользователь нажал кнопку             |
| `onBotStarted`          | `BotStartedEvent`          | Начат диалог с ботом                  |
| `onBotStopped`          | `BotStoppedEvent`          | Диалог с ботом приостановлен          |
| `onBotAddedToChat`      | `BotAddedToChatEvent`      | Бот добавлен в чат или канал          |
| `onBotRemovedFromChat`  | `BotRemovedFromChatEvent`  | Бот удалён из чата или канала         |
| `onChatTitleChanged`    | `ChatTitleChangedEvent`    | Изменён заголовок чата                |
| `onDialogCleared`       | `DialogClearedEvent`       | Диалог очищен                         |
| `onDialogMuted`         | `DialogMutedEvent`         | Уведомления для диалога выключены     |
| `onDialogUnmuted`       | `DialogUnmutedEvent`       | Уведомления для диалога включены      |
| `onDialogRemoved`       | `DialogRemovedEvent`       | Диалог удалён                         |
| `onUserAddedToChat`     | `UserAddedToChatEvent`     | Пользователь добавлен в чат           |
| `onUserRemovedFromChat` | `UserRemovedFromChatEvent` | Пользователь удалён из чата           |
| `onUnknown`             | `UnknownEvent`             | Неизвестное событие                   |
| `onFallback`            | `BaseEvent`                | Любое необработанное событие          |
| `onPrepare`             | `BaseEvent`                | Предварительная обработка всех событий|
| `onFinal`               | `BaseEvent`                | Финальная обработка всех событий      |
| `onException`           | `Throwable + BaseEvent`    | Необработанное исключение             |

## Обработка команд

Для обработки команд (сообщений, начинающихся с `/`) используйте `CommandHandler`:

```php
use MaxMessenger\Bot\MaxBot\CommandHandler as BotCommandHandler;

public function register(MaxBot $bot): void
{
    $cmdHandler = $bot->getCommandHandler();

    $cmdHandler->onCommand('start', function (MessageCreatedEvent $event): bool {
        $event->reply('Добро пожаловать!', true);
        return true;
    });

    $cmdHandler->onCommand('help', function (MessageCreatedEvent $event): bool {
        $event->reply("Доступные команды:\n/start — начать\n/help — помощь", true);
        return true;
    });

    // Обработчик для любой нераспознанной команды
    $cmdHandler->onCommands(function (MessageCreatedEvent $event): bool {
        $command = $event->userData['__command'];
        $event->reply("Неизвестная команда: /{$command}", true);
        return true;
    });
}
```

Имя выполняемой команды и дополнительные данные доступны через `$event->userData`:

```php
$command = $event->userData['__command'];  // 'start', 'help', ...
$payload = $event->userData['__payload'];  // данные после разделителя (или null)
```

## Обработка нажатий кнопок (Callbacks)

### Через `CallbackHandler`

```php
public function register(MaxBot $bot): void
{
    $callbackHandler = $bot->addCallbackHandler(':'); // ':' — разделитель

    $callbackHandler->onCallback('confirm', function (MessageCallbackEvent $event): bool {
        $event->answer('Подтверждено!');
        return true;
    });

    $callbackHandler->onCallback('cancel', function (MessageCallbackEvent $event): bool {
        $event->answer('Отменено.');
        return true;
    });
}
```

### Через `CallbackJsonHandler`

```php
public function register(MaxBot $bot): void
{
    $jsonHandler = $bot->addCallbackJsonHandler('action');

    $jsonHandler->onCallback('buy', function (MessageCallbackEvent $event): bool {
        // callback_id = '{"action":"buy","itemId":42}'
        $event->answer('Покупка оформлена!');
        return true;
    });
}
```

## Порядок регистрации

Если несколько обработчиков регистрируют один и тот же тип события, они вызываются в порядке
регистрации. Порядок регистрации обработчиков соответствует порядку сервисов в DI-контейнере.

Для явного управления приоритетом используйте тег `max_bot.handler` с атрибутом `priority`
в `config/services.yaml`:

```yaml
services:
    App\Bot\PriorityHandler:
        tags:
            - { name: max_bot.handler, priority: 10 }

    App\Bot\DefaultHandler:
        tags:
            - { name: max_bot.handler, priority: 0 }
```

Обработчики с **большим** значением `priority` регистрируются **первыми**.

## Ручная регистрация (без autoconfigure)

Если `autoconfigure` отключён или нужна явная регистрация:

```yaml
# config/services.yaml
services:
    App\Bot\MyHandler:
        tags:
            - { name: max_bot.handler }
```

## Статус события

Возвращаемое значение из обработчика управляет дальнейшей цепочкой:

- `return true` — событие обработано, следующие обработчики **не вызываются**
- `return false` или `return` без значения — событие не обработано, цепочка **продолжается**

Дополнительные способы управления цепочкой:

```php
$event->break();           // прервать, отметить как обработанное
$event->continue();        // прервать, отметить как НЕ обработанное
$event->exit();            // прервать без изменения статуса
$event->markAsHandled();   // отметить как обработанное, не прерывая цепочку
$event->markAsUnhandled(); // отметить как НЕ обработанное, не прерывая цепочку
```
