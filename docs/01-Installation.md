# Установка и настройка

## Требования

- PHP 8.2+
- Расширение `ext-mbstring`
- Symfony 6.4 или 7.x

## Установка через Composer

```bash
composer require max-messenger-bot/symfony-bundle
```

## Регистрация бандла

Добавьте бандл в `config/bundles.php`:

```php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    // ... другие бандлы
    MaxMessenger\Bot\Bundle\MaxBotBundle::class => ['all' => true],
];
```

## Конфигурация

Создайте файл `config/packages/max_bot.yaml`:

```yaml
max_bot:
    access_token: '%env(MAX_BOT_TOKEN)%'
    webhook_secret: '%env(MAX_BOT_SECRET)%'
```

Добавьте переменные в `.env`:

```dotenv
###> max-messenger-bot/symfony-bundle ###
MAX_BOT_TOKEN=your-access-token
MAX_BOT_SECRET=your-webhook-secret
###< max-messenger-bot/symfony-bundle ###
```

Для production добавьте реальные значения в `.env.local` или переменные окружения сервера:

```dotenv
MAX_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
MAX_BOT_SECRET=my-super-secret-key
```

## Настройка маршрутов (для webhook)

Если вы планируете принимать события через webhook, подключите маршруты бандла в `config/routes.yaml`:

```yaml
max_bot:
    resource: '@MaxBotBundle/config/routes.php'
    type: php
```

Это добавит маршрут `POST /max-bot/webhook`. Если вам нужен другой URL, не подключайте этот файл
и определите маршрут самостоятельно — подробнее в разделе [Webhook](04-Webhook.md).

## Проверка установки

Убедитесь, что сервисы зарегистрированы:

```bash
php bin/console debug:container MaxBot
php bin/console debug:container MaxApiClient
```

Убедитесь, что команда доступна:

```bash
php bin/console list max-bot
```

Ожидаемый вывод:

```
Available commands for the "max-bot" namespace:
  max-bot:run-polling   Run Max Bot in long polling mode
```
