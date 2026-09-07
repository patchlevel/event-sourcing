<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Result::class)]
final class ResultTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new Result([$error = new Error('foo', 'bar', new RuntimeException('error'))]);

        self::assertSame([$error], $object->errors);
    }

    public function testEmpty(): void
    {
        self::assertSame([], Result::empty()->errors);
    }

    public function testMerge(): void
    {
        $error1 = new Error('foo', 'bar', new RuntimeException('error'));
        $error2 = new Error('baz', 'qux', new RuntimeException('error'));

        $result = Result::merge([new Result([$error1]), new Result([$error2])]);

        self::assertSame([$error1, $error2], $result->errors);
    }
}
