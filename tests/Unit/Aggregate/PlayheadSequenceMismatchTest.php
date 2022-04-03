<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use Patchlevel\EventSourcing\Aggregate\PlayheadSequenceMismatch;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\TestCase;

class PlayheadSequenceMismatchTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new PlayheadSequenceMismatch(Profile::class);

        self::assertSame(
            'The playhead sequence does not match the aggregate "Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile" playhead.',
            $exception->getMessage()
        );
    }
}
