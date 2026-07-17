<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\MissingAggregateIdForStreamName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MissingAggregateIdForStreamName::class)]
final class MissingAggregateIdForStreamNameTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new MissingAggregateIdForStreamName('profile-{id}');

        self::assertSame(
            'Missing aggregate id for stream name "profile-{id}"',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
