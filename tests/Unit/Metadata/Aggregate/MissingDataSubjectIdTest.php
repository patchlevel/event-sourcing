<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\MissingDataSubjectId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(MissingDataSubjectId::class)]
final class MissingDataSubjectIdTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new MissingDataSubjectId(Profile::class);

        self::assertSame(
            sprintf('Personal data cannot used without a subject id. Please provide a subject id for %s.', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
