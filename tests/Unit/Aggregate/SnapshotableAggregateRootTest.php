<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use Patchlevel\EventSourcing\Aggregate\PlayheadSequenceMismatch;
use Patchlevel\EventSourcing\Snapshot\Snapshot;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\MessageId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\MessagePublished;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Aggregate\SnapshotableAggregateRoot */
class SnapshotableAggregateRootTest extends TestCase
{
    public function testSerialize(): void
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('hallo@patchlevel.de');

        $profile = ProfileWithSnapshot::createProfile($id, $email);
        $snapshot = $profile->toSnapshot();

        self::assertSame('1', $snapshot->id());
        self::assertSame(1, $snapshot->playhead());
        self::assertSame(ProfileWithSnapshot::class, $snapshot->aggregate());
        self::assertSame(
            [
                'id' => '1',
                'email' => 'hallo@patchlevel.de',
            ],
            $snapshot->payload()
        );
    }

    public function testInitliazingState(): void
    {
        $eventStream = [
            MessagePublished::raise(
                ProfileId::fromString('1'),
                Message::create(
                    MessageId::fromString('2'),
                    'message value'
                )
            )->recordNow(2),
        ];

        $snapshot = new Snapshot(
            ProfileWithSnapshot::class,
            '1',
            1,
            [
                'id' => '1',
                'email' => 'profile@test.com',
            ]
        );

        $profile = ProfileWithSnapshot::createFromSnapshot($snapshot, $eventStream);

        self::assertSame('1', $profile->id()->toString());
        self::assertCount(1, $profile->messages());
    }

    public function testCreateFromSnapshot(): void
    {
        $eventStream = [
            MessagePublished::raise(
                ProfileId::fromString('1'),
                Message::create(
                    MessageId::fromString('2'),
                    'message value'
                )
            )->recordNow(2),
        ];

        $snapshot = new Snapshot(
            ProfileWithSnapshot::class,
            '1',
            1,
            [
                'id' => '1',
                'email' => 'profile@test.com',
            ]
        );

        $profile = ProfileWithSnapshot::createFromSnapshot($snapshot, $eventStream);

        self::assertSame('1', $profile->id()->toString());
        self::assertCount(1, $profile->messages());
    }

    public function testPlayheadSequenceMismatch(): void
    {
        $this->expectException(PlayheadSequenceMismatch::class);

        $eventStream = [
            MessagePublished::raise(
                ProfileId::fromString('1'),
                Message::create(
                    MessageId::fromString('2'),
                    'message value'
                )
            )->recordNow(5),
        ];

        $snapshot = new Snapshot(
            ProfileWithSnapshot::class,
            '1',
            1,
            [
                'id' => '1',
                'email' => 'profile@test.com',
            ]
        );

        $profile = ProfileWithSnapshot::createFromSnapshot($snapshot, $eventStream);

        self::assertSame('1', $profile->id()->toString());
        self::assertCount(1, $profile->messages());
    }
}
