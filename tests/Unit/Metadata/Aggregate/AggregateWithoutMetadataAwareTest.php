<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateWithoutMetadataAware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(AggregateWithoutMetadataAware::class)]
final class AggregateWithoutMetadataAwareTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new AggregateWithoutMetadataAware(Profile::class);

        self::assertSame(
            sprintf('The class "%s" does not implements AggregateRootMetadataAware', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
