<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer;

use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

class DefaultEventSerializerTest extends TestCase
{
    private DefaultEventSerializer $serializer;

    public function setUp(): void
    {
        $this->serializer = DefaultEventSerializer::createDefault([__DIR__ . '/../Fixture']);
    }

    public function testSerialize(): void
    {
        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        self::assertEquals(
            new SerializedEvent('profile_created', '{"profileId":"1","email":"info@patchlevel.de"}'),
            $this->serializer->serialize($event)
        );
    }

    public function testDeserialize(): void
    {
        $expected = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        $event = $this->serializer->deserialize(
            new SerializedEvent(
                'profile_created',
                '{"profileId":"1","email":"info@patchlevel.de"}'
            )
        );

        self::assertEquals($expected, $event);
    }
}
