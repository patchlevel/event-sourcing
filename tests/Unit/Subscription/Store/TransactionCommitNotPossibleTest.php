<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Store;

use Patchlevel\EventSourcing\Subscription\Store\TransactionCommitNotPossible;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TransactionCommitNotPossible::class)]
final class TransactionCommitNotPossibleTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new TransactionCommitNotPossible($previous = new RuntimeException('error'));

        self::assertSame(
            'Committing a transaction is not possible. Maybe your platform does not support transactional DDL.',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }
}
