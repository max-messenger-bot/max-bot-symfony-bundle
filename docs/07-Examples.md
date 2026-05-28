# Примеры

## Эхо-бот

Простейший бот, который повторяет всё, что ему написали:

```php
// src/Bot/EchoHandler.php

namespace App\Bot;

use MaxMessenger\Bot\Bundle\Contract\MaxBotHandlerInterface;
use MaxMessenger\Bot\MaxBot;
use MaxMessenger\Bot\MaxBot\Event\MessageCreatedEvent;

final class EchoHandler implements MaxBotHandlerInterface
{
    public function register(MaxBot $bot): void
    {
        $bot->onMessageCreated(function (MessageCreatedEvent $event): bool {
            $text = $event->getMessage()->getText();

            if ($text === null) {
                return false;
            }

            $event->reply($text, true);
            return true;
        });
    }
}
```

## Бот с командами

```php
// src/Bot/CommandsHandler.php

namespace App\Bot;

use MaxMessenger\Bot\Bundle\Contract\MaxBotHandlerInterface;
use MaxMessenger\Bot\MaxBot;
use MaxMessenger\Bot\MaxBot\Event\BotStartedEvent;
use MaxMessenger\Bot\MaxBot\Event\MessageCreatedEvent;
use MaxMessenger\Bot\Model\Request\NewMessageBody;

final class CommandsHandler implements MaxBotHandlerInterface
{
    public function register(MaxBot $bot): void
    {
        // Приветствие при запуске
        $bot->onBotStarted(function (BotStartedEvent $event): bool {
            $name = $event->getUser()->getFirstName();
            $event->sendToChat("Привет, {$name}! Напишите /help для списка команд.");
            return true;
        });

        $cmd = $bot->getCommandHandler();

        $cmd->onCommand('start', function (MessageCreatedEvent $event): bool {
            $event->reply('Добро пожаловать! Используйте /help для справки.', true);
            return true;
        });

        $cmd->onCommand('help', function (MessageCreatedEvent $event): bool {
            $text = "Доступные команды:\n"
                . "/start — начало работы\n"
                . "/help — эта справка\n"
                . "/info — информация о боте";

            $event->reply($text, true);
            return true;
        });

        $cmd->onCommand('info', function (MessageCreatedEvent $event): bool {
            $botInfo = $event->apiClient->getMyInfo();
            $event->reply("Я — бот {$botInfo->getName()}.", true);
            return true;
        });

        // Реакция на неизвестную команду
        $cmd->onCommands(function (MessageCreatedEvent $event): bool {
            $command = $event->userData['__command'];
            $event->reply("Неизвестная команда: /{$command}. Напишите /help.", true);
            return true;
        });
    }
}
```

## Бот с кнопками

```php
// src/Bot/KeyboardHandler.php

namespace App\Bot;

use MaxMessenger\Bot\Bundle\Contract\MaxBotHandlerInterface;
use MaxMessenger\Bot\MaxBot;
use MaxMessenger\Bot\MaxBot\Event\MessageCallbackEvent;
use MaxMessenger\Bot\MaxBot\Event\MessageCreatedEvent;
use MaxMessenger\Bot\Model\Request\NewMessageBody;

final class KeyboardHandler implements MaxBotHandlerInterface
{
    public function register(MaxBot $bot): void
    {
        $bot->onMessageCreated(function (MessageCreatedEvent $event): bool {
            if ($event->getMessage()->getText() !== 'меню') {
                return false;
            }

            $message = NewMessageBody::make('Выберите действие:');
            $message->addInlineKeyboard()
                ->addCallbackButton('Профиль', 'profile')
                ->addCallbackButton('Настройки', 'settings');
            $message->addInlineKeyboard()
                ->addLinkButton('Документация', 'https://dev.max.ru/docs-api');

            $event->reply($message, false);
            return true;
        });

        $callbackHandler = $bot->addCallbackHandler();

        $callbackHandler->onCallback('profile', function (MessageCallbackEvent $event): bool {
            $user = $event->getUser();
            $event->answer("Профиль: {$user->getFirstName()} {$user->getLastName()}");
            return true;
        });

        $callbackHandler->onCallback('settings', function (MessageCallbackEvent $event): bool {
            $event->answer('Настройки пока недоступны.');
            return true;
        });
    }
}
```

## Бот с состоянием (через Doctrine)

Пример сохранения данных между сообщениями пользователя:

```php
// src/Bot/UserStateHandler.php

namespace App\Bot;

use App\Entity\BotUser;
use App\Repository\BotUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use MaxMessenger\Bot\Bundle\Contract\MaxBotHandlerInterface;
use MaxMessenger\Bot\MaxBot;
use MaxMessenger\Bot\MaxBot\Event\BotStartedEvent;
use MaxMessenger\Bot\MaxBot\Event\MessageCreatedEvent;

final class UserStateHandler implements MaxBotHandlerInterface
{
    public function __construct(
        private readonly BotUserRepository $users,
        private readonly EntityManagerInterface $em,
    ) {}

    public function register(MaxBot $bot): void
    {
        $bot->onBotStarted(function (BotStartedEvent $event): bool {
            $userId = $event->getUserId();

            if (!$this->users->find($userId)) {
                $user = new BotUser($userId, $event->getUser()->getFirstName());
                $this->em->persist($user);
                $this->em->flush();

                $event->sendToChat('Ваш профиль создан!');
            } else {
                $event->sendToChat('С возвращением!');
            }

            return true;
        });

        $bot->onMessageCreated(function (MessageCreatedEvent $event): bool {
            $userId = $event->getUserId();
            $user = $this->users->find($userId);

            if ($user === null) {
                return false;
            }

            $user->incrementMessageCount();
            $this->em->flush();

            if ($user->getMessageCount() % 10 === 0) {
                $event->reply("Вы отправили {$user->getMessageCount()} сообщений!");
            }

            return false; // Передаём дальше для обработки текста
        });
    }
}
```

## Отправка уведомлений из сервиса

Использование `MaxApiClient` вне обработчиков:

```php
// src/Service/OrderNotificationService.php

namespace App\Service;

use MaxMessenger\Bot\MaxApiClient;
use MaxMessenger\Bot\Model\Request\NewMessageBody;

final class OrderNotificationService
{
    public function __construct(private readonly MaxApiClient $apiClient) {}

    public function notifyOrderCreated(int $userMaxId, int $orderId, float $total): void
    {
        $message = NewMessageBody::make(
            "Заказ #{$orderId} оформлен.\n"
            . "Сумма: {$total} ₽\n"
            . "Мы уведомим вас о статусе доставки."
        );

        $message->addInlineKeyboard()
            ->addCallbackButton('Подробнее', "order:{$orderId}");

        $this->apiClient->sendMessageToUser($userMaxId, $message);
    }
}
```

## Глобальный перехватчик ошибок

Логирование необработанных исключений:

```php
// src/Bot/ErrorHandler.php

namespace App\Bot;

use MaxMessenger\Bot\Bundle\Contract\MaxBotHandlerInterface;
use MaxMessenger\Bot\MaxBot;
use MaxMessenger\Bot\MaxBot\Event\BaseEvent;
use Psr\Log\LoggerInterface;

final class ErrorHandler implements MaxBotHandlerInterface
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function register(MaxBot $bot): void
    {
        $bot->onException(function (\Throwable $e, BaseEvent $event): bool {
            $this->logger->error('Bot handler exception', [
                'exception' => $e->getMessage(),
                'chatId'    => method_exists($event, 'getChatId') ? $event->getChatId() : null,
                'updateType' => $event->update->getUpdateTypeRaw(),
            ]);

            // Вернём false, чтобы исключение продолжило всплывать
            return false;
        });

        // Финальный обработчик — логируем все необработанные события
        $bot->onFinal(function (BaseEvent $event): void {
            if (!$event->isHandled) {
                $this->logger->debug('Unhandled event', [
                    'type' => $event->update->getUpdateTypeRaw(),
                ]);
            }
        });
    }
}
```

## Middleware: проверка пользователя

Используйте `onPrepare` для глобальных проверок перед любым обработчиком:

```php
// src/Bot/AuthMiddleware.php

namespace App\Bot;

use App\Repository\BannedUserRepository;
use MaxMessenger\Bot\Bundle\Contract\MaxBotHandlerInterface;
use MaxMessenger\Bot\MaxBot;
use MaxMessenger\Bot\MaxBot\Event\BaseEvent;

final class AuthMiddleware implements MaxBotHandlerInterface
{
    public function __construct(private readonly BannedUserRepository $banned) {}

    public function register(MaxBot $bot): void
    {
        $bot->onPrepare(function (BaseEvent $event): bool {
            $userId = method_exists($event, 'getUserId') ? $event->getUserId() : null;

            if ($userId !== null && $this->banned->isBanned($userId)) {
                // Остановить обработку, пометить как обработанное (ответ не отправляем)
                return true;
            }

            return false; // Продолжить обработку
        });
    }
}
```

Зарегистрируйте `AuthMiddleware` с высоким приоритетом, чтобы он выполнялся первым:

```yaml
# config/services.yaml
services:
    App\Bot\AuthMiddleware:
        tags:
            - { name: max_bot.handler, priority: 100 }
```
