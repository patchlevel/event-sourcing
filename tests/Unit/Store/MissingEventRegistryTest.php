<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\MissingEventRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(MissingEventRegistry::class)]
final class MissingEventRegistryTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new MissingEventRegistry(EventsCriterion::class);

        self::assertSame(
            sprintf('criterion %s not supported without an %s given', EventsCriterion::class, EventRegistry::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
