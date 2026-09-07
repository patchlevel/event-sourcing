<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateRollbackBatchMethod;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(DuplicateRollbackBatchMethod::class)]
final class DuplicateRollbackBatchMethodTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new DuplicateRollbackBatchMethod(Profile::class, 'methodA', 'methodB');

        self::assertSame(
            sprintf('Two methods "methodA" and "methodB" on the subscriber "%s" have been marked as "rollback batch" methods. Only one method can be defined like this.', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
