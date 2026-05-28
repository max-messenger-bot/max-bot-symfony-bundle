<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Controller;

use MaxMessenger\Bot\Exception\MaxBot\Update\BadRequestException;
use MaxMessenger\Bot\Exception\MaxBot\Update\InvalidSecretException;
use MaxMessenger\Bot\MaxBot;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class MaxBotWebhookController
{
    public function __construct(private MaxBot $bot)
    {
    }

    #[Route('/max-bot/webhook', name: 'max_bot_webhook', methods: ['POST'])]
    public function webhook(): Response
    {
        try {
            $this->bot->handleFromGlobal();
        } catch (InvalidSecretException) {
            return new Response('Forbidden', Response::HTTP_FORBIDDEN);
        } catch (BadRequestException) {
            return new Response('Bad Request', Response::HTTP_BAD_REQUEST);
        }

        return new Response('', Response::HTTP_OK);
    }
}
