<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Aggregate\AggregateChanged */
class AggregateChangedTest extends TestCase
{
    public function testEvent(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $event = ProfileCreated::raise($id, $email);

        self::assertEquals([
            'profileId' => '1',
            'email' => 'hallo@patchlevel.de',
        ], $event->payload());
    }
}
