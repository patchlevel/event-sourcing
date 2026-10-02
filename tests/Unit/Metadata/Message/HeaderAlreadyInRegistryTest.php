<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Message;

use Patchlevel\EventSourcing\Metadata\Message\HeaderAlreadyInRegistry;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\BazHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(HeaderAlreadyInRegistry::class)]
final class HeaderAlreadyInRegistryTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new HeaderAlreadyInRegistry('foo', FooHeader::class, BazHeader::class);

        self::assertSame(
            sprintf(
                'The header name "foo" is already used by "%s" and cannot be used by "%s".',
                FooHeader::class,
                BazHeader::class,
            ),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
