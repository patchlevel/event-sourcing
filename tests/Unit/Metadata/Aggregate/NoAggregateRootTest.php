<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\NoAggregateRoot;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(NoAggregateRoot::class)]
final class NoAggregateRootTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new NoAggregateRoot(Profile::class);

        self::assertSame(
            sprintf('The class "%s" does not implement AggregateRoot', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
