<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\MissingAggregateIdForStreamName;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\Snapshot;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AggregateRootMetadata::class)]
final class AggregateRootMetadataTest extends TestCase
{
    public function testInstantiate(): void
    {
        $snapshot = new Snapshot('default');

        $metadata = new AggregateRootMetadata(
            Profile::class,
            'profile',
            'id',
            [ProfileCreated::class => 'applyProfileCreated'],
            [ProfileCreated::class => true],
            false,
            $snapshot,
        );

        self::assertSame(Profile::class, $metadata->className);
        self::assertSame('profile', $metadata->name);
        self::assertSame('id', $metadata->idProperty);
        self::assertSame([ProfileCreated::class => 'applyProfileCreated'], $metadata->applyMethods);
        self::assertSame([ProfileCreated::class => true], $metadata->suppressEvents);
        self::assertFalse($metadata->suppressAll);
        self::assertSame($snapshot, $metadata->snapshot);
        self::assertSame('profile-{id}', $metadata->streamName);
        self::assertNull($metadata->autoInitializeMethod);
    }

    public function testStreamNameWithAggregateId(): void
    {
        $metadata = new AggregateRootMetadata(
            Profile::class,
            'profile',
            'id',
            [],
            [],
            false,
            null,
        );

        self::assertSame('profile-1', $metadata->streamName('1'));
    }

    public function testStreamNameWithoutAggregateId(): void
    {
        $metadata = new AggregateRootMetadata(
            Profile::class,
            'profile',
            'id',
            [],
            [],
            false,
            null,
            'profile',
        );

        self::assertSame('profile', $metadata->streamName());
    }

    public function testStreamNameMissingAggregateId(): void
    {
        $metadata = new AggregateRootMetadata(
            Profile::class,
            'profile',
            'id',
            [],
            [],
            false,
            null,
        );

        $this->expectException(MissingAggregateIdForStreamName::class);

        $metadata->streamName();
    }
}
