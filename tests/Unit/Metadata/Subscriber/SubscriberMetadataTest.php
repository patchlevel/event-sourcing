<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriberMetadata::class)]
final class SubscriberMetadataTest extends TestCase
{
    public function testEventEmittingDefaults(): void
    {
        $metadata = new SubscriberMetadata('foo');

        self::assertFalse($metadata->enableEventEmittingDuringBoot);
        self::assertFalse($metadata->disableEventEmitting);
    }
}
