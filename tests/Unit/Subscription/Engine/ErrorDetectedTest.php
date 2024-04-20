<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\ErrorDetected;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @covers \Patchlevel\EventSourcing\Subscription\Engine\ErrorDetected */
final class ErrorDetectedTest extends TestCase
{
    public function testError(): void
    {
        $errors = [
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ];

        $errorDetected = new ErrorDetected($errors);

        self::assertSame($errors, $errorDetected->errors);
        self::assertSame(
            "Error in subscription engine detected.\nSubscription id1: error1\nSubscription id2: error2",
            $errorDetected->getMessage(),
        );
    }
}
