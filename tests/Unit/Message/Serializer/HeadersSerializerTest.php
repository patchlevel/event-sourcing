<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Serializer;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\Hydrator\MetadataHydrator;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer */
final class HeadersSerializerTest extends TestCase
{
    use ProphecyTrait;

    public function testSerialize(): void
    {
        $serializer = DefaultHeadersSerializer::createFromPaths([
            __DIR__ . '/../../Fixture', // add user headers
        ]);

        $content = $serializer->serialize([
            new AggregateHeader('profile', '1', 1, new DateTimeImmutable('2020-01-01T20:00:00.000000+0100')),
            new ArchivedHeader(false),
        ]);

        self::assertEquals(
            [
                'aggregate' => '{"aggregateName":"profile","aggregateId":"1","playhead":1,"recordedOn":"2020-01-01T20:00:00+01:00"}',
                'archived' => '{"archived":false}',
            ],
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

        $deserializedMessage = $serializer->deserialize(
            [
                'aggregate' => '{"aggregateName":"profile","aggregateId":"1","playhead":1,"recordedOn":"2020-01-01T20:00:00+01:00"}',
                'archived' => '{"archived":false}',
            ],
        );

        self::assertEquals(
            [
                new AggregateHeader('profile', '1', 1, new DateTimeImmutable('2020-01-01T20:00:00.000000+0100')),
                new ArchivedHeader(false),
            ],
            $deserializedMessage,
        );
    }
}
