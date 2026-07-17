<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\ArgumentTypeIsMissing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArgumentTypeIsMissing::class)]
final class ArgumentTypeIsMissingTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new ArgumentTypeIsMissing('applyProfileCreated');

        self::assertSame(
            'The method [applyProfileCreated] is no type specified.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
