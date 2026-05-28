# Конфигурация

## Полный список параметров

```yaml
# config/packages/max_bot.yaml
max_bot:
    # Обязательный. Access token вашего бота.
    access_token: '%env(MAX_BOT_TOKEN)%'

    # Опциональный. Секретный ключ для проверки подлинности webhook-запросов.
    # Передаётся в заголовке X-Max-Bot-Api-Secret.
    # По умолчанию: null (проверка не производится).
    webhook_secret: '%env(MAX_BOT_SECRET)%'

    # Опциональный. Базовый URL API Max.
    # По умолчанию: 'https://platform-api.max.ru'
    base_url: 'https://platform-api.max.ru'

    # Опциональный. Таймаут подключения в миллисекундах.
    # 0 — ждать без ограничений.
    # По умолчанию: 5000 (5 секунд).
    connect_timeout: 5000

    # Опциональный. Таймаут запроса в миллисекундах.
    # 0 — ждать без ограничений.
    # По умолчанию: 10000 (10 секунд).
    timeout: 10000
```

## Описание параметров

### `access_token` (обязательный)

Access token бота, выданный при регистрации в Max.

```yaml
max_bot:
    access_token: '%env(MAX_BOT_TOKEN)%'
```

Рекомендуется хранить в переменных окружения, а не в коде:

```dotenv
MAX_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
```

### `webhook_secret` (опциональный)

Секретный ключ для валидации входящих webhook-запросов. Если задан, бандл проверяет,
что заголовок `X-Max-Bot-Api-Secret` в каждом запросе совпадает с этим значением.

При несовпадении возвращается ответ `403 Forbidden`.

```yaml
max_bot:
    webhook_secret: '%env(MAX_BOT_SECRET)%'
```

Настоятельно рекомендуется использовать секрет в production.

### `base_url` (опциональный)

Базовый URL API Max. Полезно для тестирования с mock-сервером или при изменении адреса API.

```yaml
max_bot:
    base_url: 'https://platform-api.max.ru'
```

### `connect_timeout` (опциональный)

Максимальное время ожидания установки соединения с API (в миллисекундах). `0` — без ограничений.

```yaml
max_bot:
    connect_timeout: 5000  # 5 секунд
```

### `timeout` (опциональный)

Максимальное время выполнения запроса к API (в миллисекундах). `0` — без ограничений.

> **Важно для long polling:** при запуске `handleFromServer()` с `$timeout = 60` сервер
> держит соединение до 60 секунд. Параметр `timeout` должен быть больше, чем время long polling.
> Рекомендуется устанавливать `timeout: 0` или значение с запасом (например, 90000) при использовании
> long polling.

```yaml
max_bot:
    timeout: 10000  # 10 секунд — для webhook
    # timeout: 0    # для long polling
```

## Пример для разных окружений

Используйте `config/packages/dev/max_bot.yaml` для переопределения в dev-окружении:

```yaml
# config/packages/dev/max_bot.yaml
max_bot:
    access_token: '%env(MAX_BOT_TOKEN_DEV)%'
    webhook_secret: ~
```
