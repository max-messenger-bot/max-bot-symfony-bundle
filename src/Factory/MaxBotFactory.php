<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Factory;

use MaxMessenger\Bot\Bundle\Contract\MaxBotHandlerInterface;
use MaxMessenger\Bot\MaxApiClient;
use MaxMessenger\Bot\MaxBot;

final readonly class MaxBotFactory
{
    /**
     * @param iterable<MaxBotHandlerInterface> $handlers
     */
    public function __construct(
        private MaxApiClient $apiClient,
        private ?string $webhookSecret,
        private iterable $handlers,
    ) {
    }

    public function create(): MaxBot
    {
        $bot = new MaxBot($this->apiClient, $this->webhookSecret !== '' ? $this->webhookSecret : null);

        foreach ($this->handlers as $handler) {
            $handler->register($bot);
        }

        return $bot;
    }
}
