<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus\Serializer;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\EventBus\Serializer\DeserializeFailed;
use Patchlevel\EventSourcing\EventBus\Serializer\EventSerializerMessageSerializer;
use Patchlevel\EventSourcing\EventBus\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\EventBus\Serializer\SerializedHeader;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\Hydrator\MetadataHydrator;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\EventBus\Serializer\EventSerializerMessageSerializer */
final class HeadersSerializerTest extends TestCase
{
    use ProphecyTrait;

    public function testSerialize(): void
    {
        $serializer = new DefaultHeadersSerializer(
            (new AttributeMessageHeaderRegistryFactory())->create([
                __DIR__ . '/../../../../src', # add our headers
                __DIR__ . '/../../Fixture', # add user headers
            ]),
            new MetadataHydrator(),
            new JsonEncoder(),
        );

        $content = $serializer->serialize([
            new AggregateHeader('profile', '1', 1, new DateTimeImmutable('2020-01-01T20:00:00.000000+0100')),
            new ArchivedHeader(false)
        ]);

        self::assertEquals(
            [
                new SerializedHeader('aggregate', '{"aggregateName":"profile","aggregateId":"1","playhead":1,"recordedOn":"2020-01-01T20:00:00+01:00"}'),
                new SerializedHeader('archived', '{"archived":false}'),
            ],
            $content
        );
    }

    public function testDeserialize(): void
    {
        $serializer = new DefaultHeadersSerializer(
            (new AttributeMessageHeaderRegistryFactory())->create([
                __DIR__ . '/../../../../src', # add our headers
                __DIR__ . '/../../Fixture', # add user headers
            ]),
            new MetadataHydrator(),
            new JsonEncoder(),
        );

        $deserializedMessage = $serializer->deserialize(
            [
                new SerializedHeader('aggregate', '{"aggregateName":"profile","aggregateId":"1","playhead":1,"recordedOn":"2020-01-01T20:00:00+01:00"}'),
                new SerializedHeader('archived', '{"archived":false}'),
            ]
        );

        self::assertEquals(
            [
                new AggregateHeader('profile', '1', 1, new DateTimeImmutable('2020-01-01T20:00:00.000000+0100')),
                new ArchivedHeader(false)
            ],
            $deserializedMessage
        );
    }
}
