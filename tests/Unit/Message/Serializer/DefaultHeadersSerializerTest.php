<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Serializer;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;
use Patchlevel\Hydrator\Extension\Upcast\UpcastExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function is_string;

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
            null,
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

    public function testDeserializeWithUpcaster(): void
    {
        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new UpcastExtension(
                beforeEncoding: [
                    CallbackUpcaster::forClass(
                        StreamNameHeader::class,
                        static function (array $data): array {
                            $streamName = $data['streamName'];

                            if (is_string($streamName)) {
                                $data['streamName'] = 'profile-' . $streamName;
                            }

                            return $data;
                        },
                    ),
                ],
            ))
            ->build();

        $serializer = new DefaultHeadersSerializer(
            (new AttributeMessageHeaderRegistryFactory())->create([
                __DIR__ . '/../../Fixture',
            ]),
            $hydrator,
            new JsonEncoder(),
        );

        $deserializedMessage = $serializer->deserialize('{"streamName":{"streamName":"1"}}');

        self::assertEquals([new StreamNameHeader('profile-1')], $deserializedMessage);
    }
}
