<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer;

use Patchlevel\EventSourcing\Serializer\DeserializationNotPossible;
use Patchlevel\EventSourcing\Serializer\JsonSerializer;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\NotNormalizedProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

class JsonSerializerTest extends TestCase
{
    public function testSerialize(): void
    {
        $event = ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        self::assertSame(
            '{"profileId":"1","email":"info@patchlevel.de"}',
            (new JsonSerializer())->serialize($event)
        );
    }

    public function testSerializeNotNormalizedEvent(): void
    {
        $event = NotNormalizedProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        self::assertSame(
            '{"profileId":{},"email":{}}',
            (new JsonSerializer())->serialize($event)
        );
    }

    public function testDeserialize(): void
    {
        $expected = ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        $event = (new JsonSerializer())->deserialize(
            ProfileCreated::class,
            '{"profileId":"1","email":"info@patchlevel.de"}'
        );

        self::assertEquals($expected, $event);
    }

    public function testDeserializeWithSyntaxError(): void
    {
        $this->expectException(DeserializationNotPossible::class);

        (new JsonSerializer())->deserialize(ProfileCreated::class, '');
    }
}
