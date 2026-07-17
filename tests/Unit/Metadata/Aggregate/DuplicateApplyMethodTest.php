<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\DuplicateApplyMethod;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(DuplicateApplyMethod::class)]
final class DuplicateApplyMethodTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new DuplicateApplyMethod(Profile::class, ProfileCreated::class, 'applyA', 'applyB');

        self::assertSame(
            sprintf('Two methods "applyA" and "applyB" on the aggregate "%s" want to apply the same event "%s". Only one method can apply an event.', Profile::class, ProfileCreated::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
