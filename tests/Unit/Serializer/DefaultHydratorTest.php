<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer;

use Patchlevel\EventSourcing\Serializer\DefaultHydrator;
use Patchlevel\EventSourcing\Serializer\TypeMismatch;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\EmptyEvent;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\SimpleEvent;
use PHPUnit\Framework\TestCase;

class DefaultHydratorTest extends TestCase
{
    public function testHydrateEmptyObject(): void
    {
        $expected = new EmptyEvent();
        $event = (new DefaultHydrator())->hydrate(EmptyEvent::class, []);

        self::assertEquals($expected, $event);
    }

    public function testHydrateSimpleEvent(): void
    {
        $expected = new SimpleEvent();
        $expected->name = 'test';

        $event = (new DefaultHydrator())->hydrate(
            SimpleEvent::class,
            ['name' => 'test']
        );

        self::assertEquals($expected, $event);
    }

    public function testHydrateEventWithNullable(): void
    {
        $expected = new SimpleEvent();
        $expected->name = null;

        $event = (new DefaultHydrator())->hydrate(
            SimpleEvent::class,
            []
        );

        self::assertEquals($expected, $event);
    }

    public function testHydrateWithNormalizer(): void
    {
        $expected = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        $event = (new DefaultHydrator())->hydrate(
            ProfileCreated::class,
            [
                'profileId' => '1',
                'email' => 'info@patchlevel.de',
            ]
        );

        self::assertEquals($expected, $event);
    }

    public function testHydrateWithInvalidType(): void
    {
        $this->expectException(TypeMismatch::class);

        (new DefaultHydrator())->hydrate(ProfileCreated::class, []);
    }

    public function testExtractEmptyObject(): void
    {
        $event = new EmptyEvent();

        $expected = [];
        $data = (new DefaultHydrator())->extract($event);

        self::assertEquals($expected, $data);
    }

    public function testExtractSimpleEvent(): void
    {
        $event = new SimpleEvent();
        $event->name = 'test';

        $expected = ['name' => 'test'];

        $data = (new DefaultHydrator())->extract($event);

        self::assertEquals($expected, $data);
    }

    public function testExtractEventWithNullable(): void
    {
        $event = new SimpleEvent();
        $event->name = null;

        $expected = ['name' => null];

        $data = (new DefaultHydrator())->extract($event);

        self::assertEquals($expected, $data);
    }

    public function testExtractWithNormalizer(): void
    {
        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de')
        );

        $expected = [
            'profileId' => '1',
            'email' => 'info@patchlevel.de',
        ];

        $data = (new DefaultHydrator())->extract($event);

        self::assertEquals($expected, $data);
    }
}
