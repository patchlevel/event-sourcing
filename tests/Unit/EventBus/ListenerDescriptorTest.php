<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\EventBus\ListenerDescriptor;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\DummyListener;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\EventBus\ListenerDescriptor */
final class ListenerDescriptorTest extends TestCase
{
    public function testObjectMethod(): void
    {
        $listener = new DummyListener();

        $descriptor = new ListenerDescriptor($listener->__invoke(...));

        self::assertEquals($listener->__invoke(...), $descriptor->callable());
        self::assertEquals('Patchlevel\EventSourcing\Tests\Unit\Fixture\DummyListener::__invoke', $descriptor->name());
    }

    public function testStaticObjectMethod(): void
    {
        $listener = new DummyListener();

        $descriptor = new ListenerDescriptor([$listener, 'foo']);

        self::assertEquals($listener->__invoke(...), $descriptor->callable());
        self::assertEquals('Patchlevel\EventSourcing\Tests\Unit\Fixture\DummyListener::foo', $descriptor->name());
    }

    #[RequiresPhp('>= 8.2')]
    public function testAnonymousFunction(): void
    {
        $listener = static function (): void {
        };

        $descriptor = new ListenerDescriptor($listener(...));

        self::assertEquals($listener(...), $descriptor->callable());
        self::assertEquals('Closure', $descriptor->name());
    }

    public function testAnonymousClass(): void
    {
        $listener = new class {
            public function __invoke(): void
            {
            }
        };

        $descriptor = new ListenerDescriptor($listener->__invoke(...));

        self::assertEquals($listener->__invoke(...), $descriptor->callable());
        self::assertStringContainsString('class@anonymous', $descriptor->name());
        self::assertStringContainsString(__FILE__, $descriptor->name());
    }
}
