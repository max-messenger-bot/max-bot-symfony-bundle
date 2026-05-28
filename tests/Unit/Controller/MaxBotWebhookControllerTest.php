<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Tests\Unit\Controller;

use Codeception\Test\Unit;
use MaxMessenger\Bot\Bundle\Controller\MaxBotWebhookController;
use MaxMessenger\Bot\MaxApiConfig;
use MaxMessenger\Bot\MaxBot;
use Symfony\Component\HttpFoundation\Response;

final class MaxBotWebhookControllerTest extends Unit
{
    public function testReturns400WhenRequestIsNotPost(): void
    {
        $savedServer = $_SERVER;
        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            unset($_SERVER['CONTENT_TYPE'], $_SERVER['CONTENT_LENGTH']);

            $response = $this->makeController()->webhook();

            self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        } finally {
            $_SERVER = $savedServer;
        }
    }

    public function testReturns400WhenContentTypeIsNotJson(): void
    {
        $savedServer = $_SERVER;
        try {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['CONTENT_TYPE'] = 'text/plain';
            $_SERVER['CONTENT_LENGTH'] = '5';

            $response = $this->makeController()->webhook();

            self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        } finally {
            $_SERVER = $savedServer;
        }
    }

    public function testReturns403WhenSecretIsWrong(): void
    {
        $savedServer = $_SERVER;
        try {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['CONTENT_TYPE'] = 'application/json';
            $_SERVER['CONTENT_LENGTH'] = '5';
            $_SERVER['HTTP_X_MAX_BOT_API_SECRET'] = 'wrong-secret';

            $bot = new MaxBot(new MaxApiConfig('test-token'), 'correct-secret');
            $response = (new MaxBotWebhookController($bot))->webhook();

            self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        } finally {
            $_SERVER = $savedServer;
        }
    }

    private function makeController(): MaxBotWebhookController
    {
        return new MaxBotWebhookController(new MaxBot(new MaxApiConfig('test-token')));
    }
}
