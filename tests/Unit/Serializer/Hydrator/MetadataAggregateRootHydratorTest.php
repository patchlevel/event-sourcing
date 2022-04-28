<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer\Hydrator;

use Patchlevel\EventSourcing\Serializer\Hydrator\AggregateRootHydrator;
use Patchlevel\EventSourcing\Serializer\Hydrator\MetadataAggregateRootHydrator;
use Patchlevel\EventSourcing\Serializer\Hydrator\MissingPlayhead;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use PHPUnit\Framework\TestCase;

class MetadataAggregateRootHydratorTest extends TestCase
{
    private AggregateRootHydrator $hydrator;

    public function setUp(): void
    {
        $this->hydrator = new MetadataAggregateRootHydrator();
    }

    public function testExtract(): void
    {
        $aggregate = ProfileWithSnapshot::createProfile(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        self::assertEquals(
            ['id' => '1', 'email' => 'info@patchlevel.de', 'messages' => [], '_playhead' => 1],
            $this->hydrator->extract($aggregate)
        );
    }

    public function testHydrate(): void
    {
        $aggregate = $this->hydrator->hydrate(
            ProfileWithSnapshot::class,
            ['id' => '1', 'email' => 'info@patchlevel.de', 'messages' => [], '_playhead' => 1],
        );

        self::assertEquals(ProfileId::fromString('1'), $aggregate->id());
        self::assertEquals(Email::fromString('info@patchlevel.de'), $aggregate->email());
        self::assertEquals(1, $aggregate->playhead());
    }

    public function testMissingPlayhead(): void
    {
        $this->expectException(MissingPlayhead::class);

        $this->hydrator->hydrate(
            ProfileWithSnapshot::class,
            ['id' => '1', 'email' => 'info@patchlevel.de', 'messages' => []],
        );
    }
}
