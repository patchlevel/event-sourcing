<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Serializer;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Message\MissingHeaders;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Message\Serializer\InvalidArgument;
use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Message\HeaderNameNotRegistered;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\Hydrator\MetadataHydrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultHeadersSerializer::class)]
final class DefaultHeadersSerializerTest extends TestCase
{
    public function testSerialize(): void
    {
        $serializer = DefaultHeadersSerializer::createFromPaths([
            __DIR__ . '/../../Fixture',
        ]);

        $content = $serializer->serialize([
            new StreamNameHeader('profile-1'),
            new PlayheadHeader(1),
            new RecordedOnHeader(new DateTimeImmutable('2020-01-01T20:00:00.000000+0100')),
            new ArchivedHeader(),
        ]);

        self::assertEquals(
            '{"streamName":{"streamName":"profile-1"},"playhead":{"playhead":1},"recordedOn":{"recordedOn":"2020-01-01T20:00:00+01:00"},"archived":[]}',
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

        $deserializedMessage = $serializer->deserialize('{"streamName":{"streamName":"profile-1"},"playhead":{"playhead":1},"recordedOn":{"recordedOn":"2020-01-01T20:00:00+01:00"},"archived":[]}');

        self::assertEquals(
            [
                new StreamNameHeader('profile-1'),
                new PlayheadHeader(1),
                new RecordedOnHeader(new DateTimeImmutable('2020-01-01T20:00:00.000000+0100')),
                new ArchivedHeader(),
            ],
            $deserializedMessage,
        );
    }

    public function testDeserializeUnknownHeadersAsMissingHeaders(): void
    {
        $serializer = new DefaultHeadersSerializer(
            (new AttributeMessageHeaderRegistryFactory())->create([
                __DIR__ . '/../../Fixture',
            ]),
            new MetadataHydrator(),
            new JsonEncoder(),
            ['removed', 'alsoRemoved'],
        );

        $deserializedMessage = $serializer->deserialize('{"streamName":{"streamName":"profile-1"},"removed":{"foo":"bar"},"alsoRemoved":{"baz":1}}');

        self::assertEquals(
            [
                new StreamNameHeader('profile-1'),
                new MissingHeaders([
                    'removed' => ['foo' => 'bar'],
                    'alsoRemoved' => ['baz' => 1],
                ]),
            ],
            $deserializedMessage,
        );
    }

    public function testDeserializeUnknownHeaderNotConfiguredCrashes(): void
    {
        $serializer = new DefaultHeadersSerializer(
            (new AttributeMessageHeaderRegistryFactory())->create([
                __DIR__ . '/../../Fixture',
            ]),
            new MetadataHydrator(),
            new JsonEncoder(),
            ['removed'],
        );

        $this->expectException(HeaderNameNotRegistered::class);

        $serializer->deserialize('{"streamName":{"streamName":"profile-1"},"removed":{"foo":"bar"},"notListed":{"baz":1}}');
    }

    public function testDeserializeWildcardHandlesAllUnknownHeaders(): void
    {
        $serializer = new DefaultHeadersSerializer(
            (new AttributeMessageHeaderRegistryFactory())->create([
                __DIR__ . '/../../Fixture',
            ]),
            new MetadataHydrator(),
            new JsonEncoder(),
            ['*'],
        );

        $deserializedMessage = $serializer->deserialize('{"streamName":{"streamName":"profile-1"},"removed":{"foo":"bar"},"alsoRemoved":{"baz":1}}');

        self::assertEquals(
            [
                new StreamNameHeader('profile-1'),
                new MissingHeaders([
                    'removed' => ['foo' => 'bar'],
                    'alsoRemoved' => ['baz' => 1],
                ]),
            ],
            $deserializedMessage,
        );
    }

    public function testSerializeMissingHeadersRoundTrip(): void
    {
        $serializer = new DefaultHeadersSerializer(
            (new AttributeMessageHeaderRegistryFactory())->create([
                __DIR__ . '/../../Fixture',
            ]),
            new MetadataHydrator(),
            new JsonEncoder(),
        );

        $content = $serializer->serialize([
            new StreamNameHeader('profile-1'),
            new MissingHeaders([
                'removed' => ['foo' => 'bar'],
                'alsoRemoved' => ['baz' => 1],
            ]),
        ]);

        self::assertEquals(
            '{"streamName":{"streamName":"profile-1"},"removed":{"foo":"bar"},"alsoRemoved":{"baz":1}}',
            $content,
        );
    }

    public function testDeserializeWithInvalidHeaderPayload(): void
    {
        $serializer = new DefaultHeadersSerializer(
            (new AttributeMessageHeaderRegistryFactory())->create([
                __DIR__ . '/../../Fixture',
            ]),
            new MetadataHydrator(),
            new JsonEncoder(),
        );

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('header payload must be an array');

        $serializer->deserialize('{"streamName":"profile-1"}');
    }

    public function testCreateDefault(): void
    {
        $serializer = DefaultHeadersSerializer::createDefault();

        $content = $serializer->serialize([new StreamNameHeader('profile-1')]);

        self::assertEquals('{"streamName":{"streamName":"profile-1"}}', $content);
        self::assertEquals([new StreamNameHeader('profile-1')], $serializer->deserialize($content));
    }
}
