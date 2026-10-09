<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\SubjectIdAndPersonalDataConflict;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(SubjectIdAndPersonalDataConflict::class)]
final class SubjectIdAndPersonalDataConflictTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new SubjectIdAndPersonalDataConflict(Profile::class, 'email');

        self::assertSame(
            sprintf('Personal data cannot be used as a subject id. Fix subject id for %s::email.', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
