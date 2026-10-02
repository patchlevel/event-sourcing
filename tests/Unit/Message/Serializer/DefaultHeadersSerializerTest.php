<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Serializer;

use Patchlevel\EventSourcing\Attribute\Header;
use Patchlevel\EventSourcing\Message\MissingHeaders;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Message\Serializer\InvalidArgument;
use Patchlevel\EventSourcing\Metadata\FilesystemClassLocator;
use Patchlevel\EventSourcing\Metadata\InMemoryClassLocator;
use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Message\HeaderClassNotRegistered;
use Patchlevel\EventSourcing\Metadata\Message\HeaderNameNotRegistered;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\BazHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;
use Patchlevel\Hydrator\Extension\Upcast\UpcastExtension;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;
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
            new FooHeader('foo'),
            new BazHeader('baz'),
        ]);

        self::assertEquals(
            '{"foo":{"data":"foo"},"baz":{"data":"baz"}}',
            $content,
        );
    }

    public function testSerializeNotRegisteredHeader(): void
    {
        $serializer = DefaultHeadersSerializer::createFromPaths([
            __DIR__ . '/../../Fixture',
        ]);

        $this->expectException(HeaderClassNotRegistered::class);

        $serializer->serialize([new StreamNameHeader('profile-1')]);
    }

    public function testDeserialize(): void
    {
        $serializer = new DefaultHeadersSerializer(
            (new AttributeMessageHeaderRegistryFactory())->create(
                new FilesystemClassLocator([__DIR__ . '/../../Fixture'], Header::class),
            ),
            new StackHydrator(),
            new JsonEncoder(),
        );

        $deserializedMessage = $serializer->deserialize('{"foo":{"data":"foo"},"baz":{"data":"baz"}}');

        self::assertEquals(
            [
                new FooHeader('foo'),
                new BazHeader('baz'),
            ],
            $deserializedMessage,
        );
    }

    public function testDeserializeUnknownHeadersAsMissingHeaders(): void
    {
        $serializer = new DefaultHeadersSerializer(
            (new AttributeMessageHeaderRegistryFactory())->create(
                new FilesystemClassLocator([__DIR__ . '/../../Fixture'], Header::class),
            ),
            new StackHydrator(),
            new JsonEncoder(),
            ['removed', 'alsoRemoved'],
        );

        $deserializedMessage = $serializer->deserialize('{"foo":{"data":"foo"},"removed":{"foo":"bar"},"alsoRemoved":{"baz":1}}');

        self::assertEquals(
            [
                new FooHeader('foo'),
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
            (new AttributeMessageHeaderRegistryFactory())->create(
                new FilesystemClassLocator([__DIR__ . '/../../Fixture'], Header::class),
            ),
            new StackHydrator(),
            new JsonEncoder(),
            ['removed'],
        );

        $this->expectException(HeaderNameNotRegistered::class);

        $serializer->deserialize('{"foo":{"data":"foo"},"removed":{"foo":"bar"},"notListed":{"baz":1}}');
    }

    public function testDeserializeWildcardHandlesAllUnknownHeaders(): void
    {
        $serializer = new DefaultHeadersSerializer(
            (new AttributeMessageHeaderRegistryFactory())->create(
                new FilesystemClassLocator([__DIR__ . '/../../Fixture'], Header::class),
            ),
            new StackHydrator(),
            new JsonEncoder(),
            ['*'],
        );

        $deserializedMessage = $serializer->deserialize('{"foo":{"data":"foo"},"removed":{"foo":"bar"},"alsoRemoved":{"baz":1}}');

        self::assertEquals(
            [
                new FooHeader('foo'),
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
            (new AttributeMessageHeaderRegistryFactory())->create(
                new FilesystemClassLocator([__DIR__ . '/../../Fixture'], Header::class),
            ),
            new StackHydrator(),
            new JsonEncoder(),
        );

        $content = $serializer->serialize([
            new FooHeader('foo'),
            new MissingHeaders([
                'removed' => ['foo' => 'bar'],
                'alsoRemoved' => ['baz' => 1],
            ]),
        ]);

        self::assertEquals(
            '{"foo":{"data":"foo"},"removed":{"foo":"bar"},"alsoRemoved":{"baz":1}}',
            $content,
        );
    }

    public function testDeserializeWithInvalidHeaderPayload(): void
    {
        $serializer = new DefaultHeadersSerializer(
            (new AttributeMessageHeaderRegistryFactory())->create(
                new FilesystemClassLocator([__DIR__ . '/../../Fixture'], Header::class),
            ),
            new StackHydrator(),
            new JsonEncoder(),
        );

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('header payload must be an array');

        $serializer->deserialize('{"foo":"foo"}');
    }

    public function testCreateFromLocator(): void
    {
        $serializer = DefaultHeadersSerializer::createFromLocator(
            new InMemoryClassLocator([FooHeader::class]),
        );

        $content = $serializer->serialize([new FooHeader('bar')]);

        self::assertSame('{"foo":{"data":"bar"}}', $content);
        self::assertEquals([new FooHeader('bar')], $serializer->deserialize($content));
    }

    public function testCreateFromLocatorWithGracefulMissingHeaders(): void
    {
        $serializer = DefaultHeadersSerializer::createFromLocator(
            new InMemoryClassLocator([FooHeader::class]),
            ['removed'],
        );

        self::assertEquals(
            [
                new FooHeader('bar'),
                new MissingHeaders(['removed' => ['foo' => 'bar']]),
            ],
            $serializer->deserialize('{"foo":{"data":"bar"},"removed":{"foo":"bar"}}'),
        );
    }

    public function testCreateFromLocatorWithCustomHydrator(): void
    {
        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new UpcastExtension([
                CallbackUpcaster::forClass(
                    FooHeader::class,
                    static fn (array $data): array => ['data' => 'upcasted'],
                ),
            ]))
            ->build();

        $serializer = DefaultHeadersSerializer::createFromLocator(
            new InMemoryClassLocator([FooHeader::class]),
            hydrator: $hydrator,
        );

        self::assertEquals(
            [new FooHeader('upcasted')],
            $serializer->deserialize('{"foo":{"data":"bar"}}'),
        );
    }

    public function testCreateDefault(): void
    {
        $serializer = DefaultHeadersSerializer::createDefault();

        self::assertSame('[]', $serializer->serialize([]));

        $this->expectException(HeaderNameNotRegistered::class);

        $serializer->deserialize('{"foo":{"data":"bar"}}');
    }

    public function testDeserializeWithCustomHydrator(): void
    {
        $hydrator = (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->useExtension(new UpcastExtension([
                CallbackUpcaster::forClass(
                    FooHeader::class,
                    static function (array $data): array {
                        self::assertIsString($data['id']);

                        return ['data' => 'foo-' . $data['id']];
                    },
                ),
            ]))
            ->build();

        $serializer = DefaultHeadersSerializer::createFromPaths(
            [__DIR__ . '/../../Fixture'],
            hydrator: $hydrator,
        );

        self::assertEquals(
            [new FooHeader('foo-1')],
            $serializer->deserialize('{"foo":{"id":"1"}}'),
        );
    }
}
