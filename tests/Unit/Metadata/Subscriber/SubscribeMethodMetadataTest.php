<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscribeMethodMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;

#[CoversClass(SubscribeMethodMetadata::class)]
final class SubscribeMethodMetadataTest extends TestCase
{
    public function testInstantiate(): void
    {
        $argument = new ArgumentMetadata('message', Type::string());

        $metadata = new SubscribeMethodMetadata('onProfileCreated', [$argument]);

        self::assertSame('onProfileCreated', $metadata->name);
        self::assertSame([$argument], $metadata->arguments);
    }

    public function testInstantiateWithDefaults(): void
    {
        $metadata = new SubscribeMethodMetadata('onProfileCreated');

        self::assertSame('onProfileCreated', $metadata->name);
        self::assertSame([], $metadata->arguments);
    }
}
