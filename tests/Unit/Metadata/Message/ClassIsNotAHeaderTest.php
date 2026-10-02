<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Message;

use Patchlevel\EventSourcing\Metadata\Message\ClassIsNotAHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(ClassIsNotAHeader::class)]
final class ClassIsNotAHeaderTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new ClassIsNotAHeader(ProfileCreated::class);

        self::assertSame(
            sprintf('class %s is not a header', ProfileCreated::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
