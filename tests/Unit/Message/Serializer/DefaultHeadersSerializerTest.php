<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Serializer;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Message\Serializer\UnknownHeader;
use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Message\HeaderNameNotRegistered;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistry;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\Hydrator\MetadataHydrator;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer */
final class DefaultHeadersSerializerTest extends TestCase
{
    use ProphecyTrait;

    public function testSerialize(): void
    {
        $serializer = DefaultHeadersSerializer::createFromPaths([
            __DIR__ . '/../../Fixture',
        ]);

        $content = $serializer->serialize([
            new AggregateHeader('profile', '1', 1, new DateTimeImmutable('2020-01-01T20:00:00.000000+0100')),
            new ArchivedHeader(),
        ]);

        self::assertEquals(
            '{"aggregate":{"aggregateName":"profile","aggregateId":"1","playhead":1,"recordedOn":"2020-01-01T20:00:00+01:00"},"archived":[]}',
            $content,
        );
    }

    public function testDeserialize(): void
    {
        $serializer = new DefaultHeadersSerializer(
            (new AttributeMessageHeaderRegistryFactory())->create([
                __DIR__ . '/../../Fixture',
            ]),
            new MetadataHydrator(),
            new JsonEncoder(),
        );

        $deserializedMessage = $serializer->deserialize('{"aggregate":{"aggregateName":"profile","aggregateId":"1","playhead":1,"recordedOn":"2020-01-01T20:00:00+01:00"},"archived":[]}');

        self::assertEquals(
            [
                new AggregateHeader('profile', '1', 1, new DateTimeImmutable('2020-01-01T20:00:00.000000+0100')),
                new ArchivedHeader(),
            ],
            $deserializedMessage,
        );
    }

    public function testDeserializeUnknown(): void
    {
        $this->expectException(HeaderNameNotRegistered::class);

        $serializer = new DefaultHeadersSerializer(
            new MessageHeaderRegistry([]),
            new MetadataHydrator(),
            new JsonEncoder(),
        );

        $serializer->deserialize('{"aggregate":{"aggregateName":"profile","aggregateId":"1","playhead":1,"recordedOn":"2020-01-01T20:00:00+01:00"},"archived":[]}');
    }

    public function testDeserializeUnknownFallback(): void
    {
        $serializer = new DefaultHeadersSerializer(
            new MessageHeaderRegistry([]),
            new MetadataHydrator(),
            new JsonEncoder(),
            false,
        );

        $deserializedMessage = $serializer->deserialize('{"aggregate":{"aggregateName":"profile","aggregateId":"1","playhead":1,"recordedOn":"2020-01-01T20:00:00+01:00"},"archived":[]}');

        self::assertCount(2, $deserializedMessage);

        $header1 = $deserializedMessage[0];

        self::assertInstanceOf(UnknownHeader::class, $header1);
        self::assertEquals('aggregate', $header1->name());
        self::assertEquals([
            'aggregateName' => 'profile',
            'aggregateId' => '1',
            'playhead' => 1,
            'recordedOn' => '2020-01-01T20:00:00+01:00',
        ], $header1->payload());

        $header2 = $deserializedMessage[1];

        self::assertInstanceOf(UnknownHeader::class, $header2);
        self::assertEquals('archived', $header2->name());
        self::assertEquals([], $header2->payload());
    }
}
