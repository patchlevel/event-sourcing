<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootAlreadyInRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AggregateRootAlreadyInRegistry::class)]
final class AggregateRootAlreadyInRegistryTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new AggregateRootAlreadyInRegistry('profile');

        self::assertSame(
            'The aggregate name "profile" is already used in the registry. Maybe you defined 2 aggregates with the same name.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
