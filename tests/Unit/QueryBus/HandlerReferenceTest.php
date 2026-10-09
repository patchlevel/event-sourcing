<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\QueryBus;

use Patchlevel\EventSourcing\QueryBus\HandlerReference;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\QueryProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HandlerReference::class)]
final class HandlerReferenceTest extends TestCase
{
    public function testInstantiate(): void
    {
        $reference = new HandlerReference(QueryProfile::class, 'handle', true);

        self::assertSame(QueryProfile::class, $reference->queryClass);
        self::assertSame('handle', $reference->method);
        self::assertTrue($reference->static);
    }
}
