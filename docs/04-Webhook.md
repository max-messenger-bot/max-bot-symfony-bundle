# Webhook

## Как это работает

Бандл предоставляет готовый контроллер `MaxBotWebhookController`, который:

1. Принимает `POST`-запрос от серверов Max
2. Проверяет заголовок `X-Max-Bot-Api-Secret` (если задан `webhook_secret`)
3. Вызывает `MaxBot::handleFromGlobal()`, который разбирает тело запроса и запускает обработчики
4. Возвращает ответ `200 OK`

При ошибке валидации возвращается:
- `403 Forbidden` — неверный секретный ключ
- `400 Bad Request` — некорректный формат запроса

## Настройка маршрута

### Вариант 1: Маршрут из бандла (URL по умолчанию)

Подключите готовый файл маршрутов в `config/routes.yaml`:

```yaml
max_bot:
    resource: '@MaxBotBundle/config/routes.php'
    type: php
```

Это добавит маршрут:

```
POST /max-bot/webhook    →   max_bot_webhook
```

### Вариант 2: Собственный URL

Не подключайте файл маршрутов из бандла. Определите свой маршрут:

```yaml
# config/routes.yaml
max_bot_webhook:
    path: /api/v1/bot/updates
    controller: MaxMessenger\Bot\Bundle\Controller\MaxBotWebhookController::webhook
    methods: [POST]
```

Или через атрибут в собственном контроллере:

```php
// src/Controller/BotController.php

use MaxMessenger\Bot\Bundle\Controller\MaxBotWebhookController as BotWebhook;
use Symfony\Component\Routing\Attribute\Route;

final class BotController
{
    public function __construct(private readonly BotWebhook $webhook) {}

    #[Route('/my-bot/updates', methods: ['POST'])]
    public function handle(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->webhook->webhook();
    }
}
```

## Регистрация webhook в Max

После того как ваш сервер доступен по HTTPS, зарегистрируйте webhook через API или используйте
метод `MaxApiClient::subscribe()`:

```php
use MaxMessenger\Bot\MaxApiClient;
use MaxMessenger\Bot\Model\Request\SubscriptionRequestBody;

$client = new MaxApiClient('your-access-token');

$subscription = new SubscriptionRequestBody('https://your-domain.com/max-bot/webhook');
$subscription->setSecretKey('your-webhook-secret');
$client->subscribe($subscription);
```

Или напрямую через консоль Symfony:

```php
// src/Command/RegisterWebhookCommand.php

use MaxMessenger\Bot\MaxApiClient;
use MaxMessenger\Bot\Model\Request\SubscriptionRequestBody;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('max-bot:register-webhook')]
final class RegisterWebhookCommand extends Command
{
    public function __construct(private readonly MaxApiClient $client)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $subscription = new SubscriptionRequestBody('https://your-domain.com/max-bot/webhook');
        $subscription->setSecretKey($_ENV['MAX_BOT_SECRET'] ?? '');
        $this->client->subscribe($subscription);

        $output->writeln('Webhook зарегистрирован.');
        return Command::SUCCESS;
    }
}
```

## Требования к серверу

- HTTPS (обязательно для production)
- Сервер должен быть доступен из интернета
- Порт 443 должен быть открыт
- Заголовки `Content-Type: application/json` и `Content-Length` передаются серверами Max автоматически

## Проверка работоспособности

Для проверки без реального сервера можно использовать [ngrok](https://ngrok.com/):

```bash
ngrok http 8000
```

Полученный HTTPS-URL используйте для регистрации webhook.

## Отладка

Включите логирование ошибок и просматривайте `var/log/dev.log`. При проблемах с валидацией:

1. Убедитесь, что `webhook_secret` в конфигурации совпадает со значением, переданным при регистрации
2. Проверьте, что запрос приходит с заголовком `X-Max-Bot-Api-Secret`
3. Убедитесь, что сервер принимает `POST`-запросы с `Content-Type: application/json`

## Безопасность

Рекомендации:

- Всегда используйте `webhook_secret` в production
- Используйте длинный случайный секрет (минимум 32 символа)
- Настройте rate limiting на роуте webhook (например, через Symfony RateLimiter)
- Не логируйте тело запроса целиком — оно может содержать данные пользователей
