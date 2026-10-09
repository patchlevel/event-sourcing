<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository;

use Patchlevel\EventSourcing\Repository\PlayheadMismatch;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(PlayheadMismatch::class)]
final class PlayheadMismatchTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new PlayheadMismatch(Profile::class, ProfileId::fromString('1'), 1, 2);

        self::assertSame(
            sprintf('There is a mismatch between the playhead [1] and the event count [2] for the aggregate [%s] with the id [1]', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
