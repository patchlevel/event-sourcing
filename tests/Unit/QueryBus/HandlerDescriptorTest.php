<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\QueryBus;

use Patchlevel\EventSourcing\Attribute\Answer;
use Patchlevel\EventSourcing\QueryBus\HandlerDescriptor;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\QueryAnsweringProjection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\QueryAnsweringStaticProjection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\QueryProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;

#[CoversClass(HandlerDescriptor::class)]
final class HandlerDescriptorTest extends TestCase
{
    public function testObjectMethod(): void
    {
        $projection = new QueryAnsweringProjection();
        $descriptor = new HandlerDescriptor($projection->answerQuery(...));

        self::assertEquals($projection->answerQuery(...), $descriptor->callable());
        self::assertEquals('Patchlevel\EventSourcing\Tests\Unit\Fixture\QueryAnsweringProjection::answerQuery', $descriptor->name());
    }

    public function testStaticObjectMethod(): void
    {
        $descriptor = new HandlerDescriptor([QueryAnsweringStaticProjection::class, 'answerQuery']);

        self::assertEquals(QueryAnsweringStaticProjection::answerQuery(...), $descriptor->callable());
        self::assertEquals('Patchlevel\EventSourcing\Tests\Unit\Fixture\QueryAnsweringStaticProjection::answerQuery', $descriptor->name());
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
        $projection = new class {
            #[Answer]
            public function answerQuery(QueryProfile $query): string
            {
                return 'found';
            }
        };

        $descriptor = new HandlerDescriptor($projection->answerQuery(...));

        self::assertEquals($projection->answerQuery(...), $descriptor->callable());
        self::assertStringContainsString('class@anonymous', $descriptor->name());
        self::assertStringContainsString(__FILE__, $descriptor->name());
    }
}
