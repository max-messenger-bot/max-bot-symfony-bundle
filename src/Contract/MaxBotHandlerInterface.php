<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Contract;

use MaxMessenger\Bot\MaxBot;

interface MaxBotHandlerInterface
{
    public function register(MaxBot $bot): void;
}
