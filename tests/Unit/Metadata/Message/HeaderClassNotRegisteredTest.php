<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Message;

use Patchlevel\EventSourcing\Metadata\Message\HeaderClassNotRegistered;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(HeaderClassNotRegistered::class)]
final class HeaderClassNotRegisteredTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new HeaderClassNotRegistered(FooHeader::class);

        self::assertSame(
            sprintf('Header class "%s" is not registered', FooHeader::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
