<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\Event\SubjectIdAndPersonalDataConflict;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(SubjectIdAndPersonalDataConflict::class)]
final class SubjectIdAndPersonalDataConflictTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new SubjectIdAndPersonalDataConflict(ProfileCreated::class, 'email');

        self::assertSame(
            sprintf('Personal data cannot be used as a subject id. Fix subject id for %s::email.', ProfileCreated::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
