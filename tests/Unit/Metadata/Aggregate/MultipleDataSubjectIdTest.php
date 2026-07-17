<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\MultipleDataSubjectId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MultipleDataSubjectId::class)]
final class MultipleDataSubjectIdTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new MultipleDataSubjectId('foo', 'bar');

        self::assertSame(
            'Multiple data subject id found: foo and bar.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
