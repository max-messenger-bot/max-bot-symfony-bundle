<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Command;

use MaxMessenger\Bot\Exception\SimpleQueryError;
use MaxMessenger\Bot\MaxApiClient;
use MaxMessenger\Bot\Model\Enum\UpdateType;
use MaxMessenger\Bot\Model\Request\SubscriptionRequestBody;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

use function assert;
use function sprintf;
use function strlen;

#[AsCommand(
    name: 'max-bot:subscribe',
    description: 'Register a webhook subscription',
)]
final class MaxBotSubscribeCommand extends Command
{
    public function __construct(
        private readonly MaxApiClient $apiClient,
        private readonly ?RouterInterface $router = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $allTypes = implode(', ', array_map(static fn(UpdateType $t): string => $t->value, UpdateType::cases()));

        $this
            ->addArgument(
                'url',
                InputArgument::OPTIONAL,
                'Webhook URL (https://, no port). Auto-detected from router if omitted.',
            )
            ->addOption(
                'secret',
                null,
                InputOption::VALUE_REQUIRED,
                'Secret key (5–256 chars, A-Z/a-z/0-9/_/-). Prompted if not provided.',
            )
            ->addOption(
                'types',
                null,
                InputOption::VALUE_REQUIRED,
                sprintf('Comma-separated event types or "all". Available: %s', $allTypes),
                'all',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $url = (string)($input->getArgument('url') ?? '');

        if ($url === '') {
            $url = $this->resolveUrl($io);
        }

        if (!$this->isValidUrl($url)) {
            $io->error('Неверный формат URL. Требуется https:// без порта.');
            return Command::FAILURE;
        }

        $secret = (string)($input->getOption('secret') ?? '');
        if ($secret === '') {
            $secret = (string)$io->askHidden(
                'Кодовое слово (5–256 символов, A-Z/a-z/0-9/_/-)',
                function (string $value): string {
                    if (!$this->isValidSecret($value)) {
                        throw new RuntimeException(
                            'Кодовое слово должно быть от 5 до 256 символов и содержать только A-Z, a-z, 0-9, _ и -'
                        );
                    }
                    return $value;
                }
            );
        } elseif (!$this->isValidSecret($secret)) {
            $io->error('Кодовое слово должно быть от 5 до 256 символов и содержать только A-Z, a-z, 0-9, _ и -');
            return Command::FAILURE;
        }

        $typesInput = strtolower((string)$input->getOption('types'));
        $updateTypeEnums = $this->parseUpdateTypes($io, $typesInput);
        if ($updateTypeEnums === false) {
            return Command::FAILURE;
        }

        $io->section('Параметры подписки');
        $io->definitionList(
            ['URL' => $url],
            [
                'Типы событий' => $updateTypeEnums === null
                    ? 'все'
                    : implode(', ', array_map(static fn(UpdateType $t): string => $t->value, $updateTypeEnums))
            ],
            ['Кодовое слово' => str_repeat('*', min(strlen($secret), 10))],
        );

        if (!$io->confirm('Сохранить подписку?')) {
            $io->text('Отменено.');
            return Command::SUCCESS;
        }

        assert($url !== '' && $secret !== '');

        try {
            $subscription = SubscriptionRequestBody::new(
                url: $url,
                secret: $secret,
                update_types: $updateTypeEnums,
            );
            $this->apiClient->subscribe($subscription);
        } catch (SimpleQueryError $e) {
            $io->error(sprintf('Ошибка API: %s', $e->getMessage()));
            return Command::FAILURE;
        } catch (Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Webhook-подписка успешно зарегистрирована.');
        return Command::SUCCESS;
    }

    private function generateWebhookUrl(): ?string
    {
        if ($this->router === null) {
            return null;
        }

        try {
            return $this->router->generate('max_bot_webhook', [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RouteNotFoundException) {
            return null;
        }
    }

    private function isValidSecret(string $secret): bool
    {
        if (strlen($secret) < 5 || strlen($secret) > 256) {
            return false;
        }

        return preg_match('/^[a-zA-Z0-9_-]+$/', $secret) === 1;
    }

    private function isValidUrl(string $url): bool
    {
        if (!str_starts_with($url, 'https://')) {
            return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parsed = parse_url($url);

        return !isset($parsed['port']);
    }

    /**
     * @return list<UpdateType>|null|false null — все типы, false — ошибка парсинга
     */
    private function parseUpdateTypes(SymfonyStyle $io, string $input): array|null|false
    {
        if ($input === 'all' || $input === '') {
            return null;
        }

        $cases = UpdateType::cases();
        $valueMap = [];
        foreach ($cases as $case) {
            $valueMap[$case->value] = $case;
        }

        $selected = [];
        foreach (array_map('trim', explode(',', $input)) as $value) {
            if ($value === '') {
                continue;
            }
            if (!isset($valueMap[$value])) {
                $io->error(
                    sprintf(
                        'Неизвестный тип события: "%s". Доступные: %s',
                        $value,
                        implode(', ', array_keys($valueMap))
                    )
                );
                return false;
            }
            $selected[] = $valueMap[$value];
        }

        if (empty($selected)) {
            return null;
        }

        return $selected;
    }

    private function resolveUrl(SymfonyStyle $io): string
    {
        $suggested = $this->generateWebhookUrl();

        if ($suggested !== null) {
            $io->text(sprintf('Обнаружен маршрут webhook: <info>%s</info>', $suggested));
        }

        return (string)$io->ask('Webhook URL (https://, без порта)', $suggested);
    }
}
