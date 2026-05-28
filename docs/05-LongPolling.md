# Long Polling

## Что такое Long Polling

Long Polling — режим работы, при котором бот сам периодически запрашивает у сервера новые события,
не требуя публичного HTTPS-адреса. Это удобно для разработки и для серверов без внешнего IP.

Принцип работы:
1. Бот отправляет запрос на сервер Max с ожиданием до `$timeout` секунд
2. Сервер возвращает список накопившихся событий (или пустой список по истечении таймаута)
3. Бот обрабатывает полученные события и повторяет запрос

> **Long polling и webhook несовместимы.** Если на боте зарегистрирована webhook-подписка,
> сервер Max не будет отдавать события через polling. Используйте `max-bot:subscriptions`
> для проверки и `max-bot:unsubscribe` для удаления подписки.

## Команды

Бандл предоставляет две команды для работы с long polling:

| Команда | Описание |
|---|---|
| `max-bot:run-polling` | Запуск бота: обрабатывает события через зарегистрированные обработчики |
| `max-bot:debug` | Выводит сырой JSON каждого события без вызова обработчиков — для отладки |

## Запуск команды

Бандл предоставляет команду `max-bot:run-polling`:

```bash
php bin/console max-bot:run-polling
```

### Параметры команды

| Параметр    | По умолчанию | Допустимые значения | Описание                                |
|-------------|-------------|---------------------|-----------------------------------------|
| `--limit`   | `100`       | 1–1000              | Максимальное количество событий за раз  |
| `--timeout` | `60`        | 0–90                | Время ожидания ответа сервера (секунды) |

Примеры:

```bash
# Получать до 50 событий, ждать 30 секунд
php bin/console max-bot:run-polling --limit=50 --timeout=30

# Режим без ожидания (polling без long polling)
php bin/console max-bot:run-polling --timeout=0
```

## Важно: настройка таймаута API

При использовании long polling параметр `timeout` в конфигурации бандла должен быть больше,
чем `--timeout` команды, иначе HTTP-запрос завершится раньше, чем сервер успеет ответить.

```yaml
# config/packages/max_bot.yaml
max_bot:
    access_token: '%env(MAX_BOT_TOKEN)%'
    timeout: 0  # 0 = без ограничений, рекомендуется для long polling
```

## Остановка команды

Команда корректно обрабатывает сигналы `SIGINT` (Ctrl+C) и `SIGTERM`:

```bash
# Остановка сигналом
kill -TERM <pid>

# Или просто Ctrl+C в терминале
^C
```

После получения сигнала команда завершит текущую итерацию и выйдет.

## Запуск в production через Supervisor

Для автозапуска и перезапуска используйте [Supervisor](http://supervisord.org/).

Создайте конфигурацию `/etc/supervisor/conf.d/max-bot.conf`:

```ini
[program:max-bot]
command=php /var/www/app/bin/console max-bot:run-polling --timeout=60
directory=/var/www/app
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/www/app/var/log/max-bot.log
stderr_logfile=/var/www/app/var/log/max-bot-error.log
stopwaitsecs=30
```

Запустите Supervisor:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start max-bot
```

Проверка статуса:

```bash
supervisorctl status max-bot
```

## Запуск через systemd

Создайте `/etc/systemd/system/max-bot.service`:

```ini
[Unit]
Description=Max Bot Long Polling
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/app
ExecStart=/usr/bin/php /var/www/app/bin/console max-bot:run-polling --timeout=60
Restart=on-failure
RestartSec=5s

[Install]
WantedBy=multi-user.target
```

Активация:

```bash
systemctl daemon-reload
systemctl enable max-bot
systemctl start max-bot
systemctl status max-bot
```

## Обработка ошибок

При возникновении исключения команда выводит сообщение об ошибке, делает паузу 5 секунд и
продолжает работу:

```
[ERROR] Connection timeout after 10000ms
# ... пауза 5 секунд ...
# ... продолжение ...
```

Это защищает от бесконечного цикла при временной недоступности сервера.

Если исключение нужно обрабатывать кастомно (например, отправлять в Sentry), используйте
`$bot->onException()` в обработчике:

```php
public function register(MaxBot $bot): void
{
    $bot->onException(function (\Throwable $exception, BaseEvent $event): void {
        $this->sentry->captureException($exception);
    });
}
```

## Отладка с max-bot:debug

Команда `max-bot:debug` работает аналогично `max-bot:run-polling`, но вместо вызова
обработчиков выводит сырой JSON каждого события. Это удобно для проверки, какие события
приходят и как выглядят их данные.

```bash
php bin/console max-bot:debug [--limit=1] [--timeout=60]
```

`max-bot:debug` и `max-bot:run-polling` **можно запускать одновременно** — каждая команда
ведёт независимую сессию с собственным маркером, поэтому обе получают все входящие события.
Это позволяет наблюдать сырые данные в одном терминале, пока бот обрабатывает события в другом.

Перед запуском команда проверяет наличие активных webhook-подписок и предупреждает,
если polling невозможен. Подробнее — в разделе [Консольные команды](08-Commands.md).

## Ограничения Long Polling

- Не подходит для высоконагруженных ботов с большим потоком событий
- Одновременно может работать только один экземпляр (иначе события будут дублироваться)
- В production webhook предпочтительнее — он реактивнее и нагружает сервер меньше
