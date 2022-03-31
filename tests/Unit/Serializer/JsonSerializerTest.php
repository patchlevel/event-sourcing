<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer;

use Patchlevel\EventSourcing\Serializer\DeserializationNotPossible;
use Patchlevel\EventSourcing\Serializer\JsonSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedData;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\NotNormalizedProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

class JsonSerializerTest extends TestCase
{
    private JsonSerializer $serializer;

    public function setUp(): void
    {
        $this->serializer = JsonSerializer::createDefault([__DIR__ . '/../Fixture']);
    }

    public function testSerialize(): void
    {
        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        self::assertEquals(
            new SerializedData('profile_created', '{"profileId":"1","email":"info@patchlevel.de"}'),
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
            new SerializedData('not_normalized_profile_created', '{"profileId":{},"email":{}}'),
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
            new SerializedData(
                'profile_created',
                '{"profileId":"1","email":"info@patchlevel.de"}'
            )
        );

        self::assertEquals($expected, $event);
    }

    public function testDeserializeWithSyntaxError(): void
    {
        $this->expectException(DeserializationNotPossible::class);

        $this->serializer->deserialize(
            new SerializedData(
                'profile_created',
                ''
            )
        );
    }
}
