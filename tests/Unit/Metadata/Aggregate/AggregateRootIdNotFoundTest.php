<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootIdNotFound;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(AggregateRootIdNotFound::class)]
final class AggregateRootIdNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new AggregateRootIdNotFound(Profile::class);

        self::assertSame(
            sprintf('class %s has no property marked as aggregate root id', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
