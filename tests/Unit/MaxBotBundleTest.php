<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\Tests\Unit;

use Codeception\Test\Unit;
use MaxMessenger\Bot\Bundle\DependencyInjection\MaxBotExtension;
use MaxMessenger\Bot\Bundle\MaxBotBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class MaxBotBundleTest extends Unit
{
    public function testIsSymfonyBundle(): void
    {
        self::assertInstanceOf(Bundle::class, new MaxBotBundle());
    }

    public function testContainerExtension(): void
    {
        self::assertInstanceOf(MaxBotExtension::class, (new MaxBotBundle())->getContainerExtension());
    }
}
