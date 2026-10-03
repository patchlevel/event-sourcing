<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Error::class)]
final class ErrorTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new Error('foo', 'something went wrong', $throwable = new RuntimeException('error'));

        self::assertSame('foo', $object->subscriptionId);
        self::assertSame('something went wrong', $object->message);
        self::assertSame($throwable, $object->throwable);
    }
}
