<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\CommandBus\HandlerDescriptor;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithHandlers;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\CommandBus\HandlerDescriptor */
final class HandlerDescriptorTest extends TestCase
{
    public function testObjectMethod(): void
    {
        $aggregate = ProfileWithHandlers::createEmpty();

        $descriptor = new HandlerDescriptor($aggregate->changeName(...));

        self::assertEquals($aggregate->changeName(...), $descriptor->callable());
        self::assertEquals('Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithHandlers::changeName', $descriptor->name());
    }

    public function testStaticObjectMethod(): void
    {
        $descriptor = new HandlerDescriptor([ProfileWithHandlers::class, 'create']);

        self::assertEquals(ProfileWithHandlers::create(...), $descriptor->callable());
        self::assertEquals('Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithHandlers::create', $descriptor->name());
    }

    #[RequiresPhp('>= 8.2')]
    public function testAnonymousFunction(): void
    {
        $handler = static function (): void {
        };

        $descriptor = new HandlerDescriptor($handler(...));

        self::assertEquals($handler(...), $descriptor->callable());
        self::assertEquals('Closure', $descriptor->name());
    }

    public function testAnonymousClass(): void
    {
        $handler = new class {
            public function __invoke(): void
            {
            }
        };

        $descriptor = new HandlerDescriptor($handler->__invoke(...));

        self::assertEquals($handler->__invoke(...), $descriptor->callable());
        self::assertStringContainsString('class@anonymous', $descriptor->name());
        self::assertStringContainsString(__FILE__, $descriptor->name());
    }
}
