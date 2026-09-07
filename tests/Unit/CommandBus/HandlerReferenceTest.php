<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\CommandBus\HandlerReference;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HandlerReference::class)]
final class HandlerReferenceTest extends TestCase
{
    public function testInstantiate(): void
    {
        $reference = new HandlerReference(CreateProfile::class, 'handle', true);

        self::assertSame(CreateProfile::class, $reference->commandClass);
        self::assertSame('handle', $reference->method);
        self::assertTrue($reference->static);
    }
}
