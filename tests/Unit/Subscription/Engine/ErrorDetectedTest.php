<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\ErrorDetected;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ErrorDetected::class)]
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
            '2 error(s) in subscription engine detected. First error is in "id1" subscription: error1',
            $errorDetected->getMessage(),
        );
        self::assertSame(
            $errors[0]->throwable,
            $errorDetected->getPrevious(),
        );
    }
}
