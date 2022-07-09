<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use Patchlevel\EventSourcing\Aggregate\ApplyMethodNotFound;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\TestCase;

final class ApplyMethodNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new ApplyMethodNotFound(Profile::class, ProfileCreated::class);

        self::assertSame(
            'Apply method in "Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile" could not be found for the event "Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated"',
            $exception->getMessage()
        );
    }
}
