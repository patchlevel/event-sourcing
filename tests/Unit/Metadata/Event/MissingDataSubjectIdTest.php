<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\Event\MissingDataSubjectId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(MissingDataSubjectId::class)]
final class MissingDataSubjectIdTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new MissingDataSubjectId(ProfileCreated::class);

        self::assertSame(
            sprintf('Personal data cannot used without a subject id. Please provide a subject id for %s.', ProfileCreated::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
