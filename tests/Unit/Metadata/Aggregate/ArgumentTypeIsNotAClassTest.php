<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\ArgumentTypeIsNotAClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArgumentTypeIsNotAClass::class)]
final class ArgumentTypeIsNotAClassTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new ArgumentTypeIsNotAClass('applyProfileCreated', 'string');

        self::assertSame(
            'The type [string] is not a valid class for method [applyProfileCreated].',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
