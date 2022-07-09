<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer\Hydrator;

use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Serializer\Hydrator\DenormalizationFailure;
use Patchlevel\EventSourcing\Serializer\Hydrator\MetadataEventHydrator;
use Patchlevel\EventSourcing\Serializer\Hydrator\NormalizationFailure;
use Patchlevel\EventSourcing\Serializer\Hydrator\TypeMismatch;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\WrongNormalizerEvent;
use PHPUnit\Framework\TestCase;

final class MetadataEventHydratorTest extends TestCase
{
    private MetadataEventHydrator $hydrator;

    public function setUp(): void
    {
        $this->hydrator = new MetadataEventHydrator(new AttributeEventMetadataFactory());
    }

    public function testExtract(): void
    {
        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        self::assertEquals(
            ['profileId' => '1', 'email' => 'info@patchlevel.de'],
            $this->hydrator->extract($event)
        );
    }

    public function testHydrate(): void
    {
        $expected = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        $event = $this->hydrator->hydrate(
            ProfileCreated::class,
            ['profileId' => '1', 'email' => 'info@patchlevel.de']
        );

        self::assertEquals($expected, $event);
    }

    public function testWithTypeMismatch(): void
    {
        $this->expectException(TypeMismatch::class);

        $this->hydrator->hydrate(
            ProfileCreated::class,
            ['profileId' => null, 'email' => null]
        );
    }

    public function testDenormalizationFailure(): void
    {
        $this->expectException(DenormalizationFailure::class);

        $this->hydrator->hydrate(
            ProfileCreated::class,
            ['profileId' => 123, 'email' => 123]
        );
    }

    public function testNormalizationFailure(): void
    {
        $this->expectException(NormalizationFailure::class);

        $this->hydrator->extract(
            new WrongNormalizerEvent(true)
        );
    }
}
