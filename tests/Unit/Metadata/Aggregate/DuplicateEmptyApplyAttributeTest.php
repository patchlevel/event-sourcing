<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\DuplicateEmptyApplyAttribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DuplicateEmptyApplyAttribute::class)]
final class DuplicateEmptyApplyAttributeTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new DuplicateEmptyApplyAttribute('apply');

        self::assertSame(
            'The method [apply] has multiple apply attributes given without an event name which is not allowed.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
