<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer;

use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Serializer\EventPayloadNotAnArray;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;
use Patchlevel\Hydrator\Extension\Upcast\UpcastExtension;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(DefaultEventSerializer::class)]
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

    public function testSerializeWithNonArrayPayload(): void
    {
        $hydrator = $this->createStub(Hydrator::class);
        $hydrator->method('extract')->willReturn('foo');

        $serializer = DefaultEventSerializer::createFromPaths([__DIR__ . '/../Fixture'], $hydrator);

        $this->expectException(EventPayloadNotAnArray::class);
        $this->expectExceptionMessage(sprintf('The event "%s" has to be extracted to an array, "string" given.', ProfileCreated::class));

        $serializer->serialize(new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        ));
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

    public function testDeserializeWithUpcasting(): void
    {
        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new UpcastExtension([
                CallbackUpcaster::forClass(
                    ProfileCreated::class,
                    static fn (array $data): array => $data + ['email' => 'info@patchlevel.de'],
                ),
            ]))
            ->build();

        $serializer = DefaultEventSerializer::createFromPaths([__DIR__ . '/../Fixture'], $hydrator);

        $expected = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $event = $serializer->deserialize(
            new SerializedEvent(
                'profile_created',
                '{"profileId":"1"}',
            ),
        );

        self::assertEquals($expected, $event);
    }
}
