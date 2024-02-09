<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Outbox;

use Patchlevel\EventSourcing\Outbox\OutboxHeaderIssue;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Outbox\OutboxHeaderIssue */
final class OutboxHeaderIssueTest extends TestCase
{
    public function testMissingHeader(): void
    {
        $error = OutboxHeaderIssue::missingHeader('foo');

        self::assertSame('missing header "foo"', $error->getMessage());
        self::assertSame(0, $error->getCode());
    }

    public function testInvalidHeaderType(): void
    {
        $error = OutboxHeaderIssue::invalidHeaderType('foo');

        self::assertSame('Invalid header given: need type "int" got "string"', $error->getMessage());
        self::assertSame(0, $error->getCode());
    }
}
