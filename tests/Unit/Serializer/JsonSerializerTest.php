<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer;

use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Serializer\Encoder\DecodeNotPossible;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\NotNormalizedProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

class JsonSerializerTest extends TestCase
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

    public function testSerializeNotNormalizedEvent(): void
    {
        $event = new NotNormalizedProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        self::assertEquals(
            new SerializedEvent('not_normalized_profile_created', '{"profileId":{},"email":{}}'),
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

    public function testDeserializeWithSyntaxError(): void
    {
        $this->expectException(DecodeNotPossible::class);

        $this->serializer->deserialize(
            new SerializedEvent(
                'profile_created',
                ''
            )
        );
    }
}
