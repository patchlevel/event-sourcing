<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer;

use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Serializer\Hydrator\MetadataEventHydrator;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcast;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;
use Patchlevel\EventSourcing\Serializer\Upcast\UpcasterChain;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

final class DefaultEventSerializerTest extends TestCase
{
    private DefaultEventSerializer $serializer;

    public function setUp(): void
    {
        $this->serializer = DefaultEventSerializer::createFromPaths([__DIR__ . '/../Fixture']);
    }

    public function testSerialize(): void
    {
        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        self::assertEquals(
            new SerializedEvent('profile_created', '{"profileId":"1","email":"info@patchlevel.de"}'),
            $this->serializer->serialize($event),
        );
    }

    public function testDeserialize(): void
    {
        $expected = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $event = $this->serializer->deserialize(
            new SerializedEvent(
                'profile_created',
                '{"profileId":"1","email":"info@patchlevel.de"}',
            ),
        );

        self::assertEquals($expected, $event);
    }

    public function testSerializeWithUpcasting(): void
    {
        $upcaster = new class implements Upcaster {
            public function __invoke(Upcast $upcast): Upcast
            {
                if ($upcast->eventName !== 'profile_created_old') {
                    return $upcast;
                }

                return new Upcast('profile_created', $upcast->payload + ['email' => 'info@patchlevel.de']);
            }
        };

        $serializer = new DefaultEventSerializer(
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/../Fixture']),
            new MetadataEventHydrator(new AttributeEventMetadataFactory()),
            new JsonEncoder(),
            $upcaster,
        );

        $expected = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $event = $serializer->deserialize(
            new SerializedEvent(
                'profile_created_old',
                '{"profileId":"1"}',
            ),
        );

        self::assertEquals($expected, $event);
    }

    public function testSerializeWithUpcastingChain(): void
    {
        $upcasterOne = new class implements Upcaster {
            public function __invoke(Upcast $upcast): Upcast
            {
                if ($upcast->eventName !== 'profile_created_very_old') {
                    return $upcast;
                }

                return new Upcast('profile_created_old', ['profileId' => $upcast->payload['id'] ?? 'None']);
            }
        };

        $upcasterTwo = new class implements Upcaster {
            public function __invoke(Upcast $upcast): Upcast
            {
                if ($upcast->eventName !== 'profile_created_old') {
                    return $upcast;
                }

                return new Upcast('profile_created', $upcast->payload + ['email' => 'info@patchlevel.de']);
            }
        };

        $serializer = new DefaultEventSerializer(
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/../Fixture']),
            new MetadataEventHydrator(new AttributeEventMetadataFactory()),
            new JsonEncoder(),
            new UpcasterChain([$upcasterOne, $upcasterTwo]),
        );

        $expected = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $event = $serializer->deserialize(
            new SerializedEvent(
                'profile_created_very_old',
                '{"id":"1"}',
            ),
        );

        self::assertEquals($expected, $event);
    }
}
