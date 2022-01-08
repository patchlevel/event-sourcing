<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Projection\AttributeHandleMethod;
use Patchlevel\EventSourcing\Projection\DuplicateHandleMethod;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\MessagePublished;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @coversNothing  */
class AttributeHandleMethodTest extends TestCase
{
    public function testHandleAttribute(): void
    {
        $projection = new class implements Projection {
            use AttributeHandleMethod;

            #[Handle(ProfileCreated::class)]
            #[Handle(ProfileVisited::class)]
            public function handleProfileCreated(ProfileCreated|ProfileVisited $event): void
            {
            }

            #[Handle(MessagePublished::class)]
            public function handlePublish(MessagePublished $event): void
            {
            }

            public function create(): void
            {
            }

            public function drop(): void
            {
            }
        };

        self::assertEquals(
            [
                ProfileCreated::class => 'handleProfileCreated',
                ProfileVisited::class => 'handleProfileCreated',
                MessagePublished::class => 'handlePublish',
            ],
            $projection->handledEvents()
        );
    }

    public function testDuplicateHandleAttribute(): void
    {
        $this->expectException(DuplicateHandleMethod::class);

        $projection = new class implements Projection {
            use AttributeHandleMethod;

            #[Handle(ProfileCreated::class)]
            public function handleProfileCreated1(ProfileCreated $event): void
            {
            }

            #[Handle(ProfileCreated::class)]
            public function handleProfileCreated2(ProfileCreated $event): void
            {
            }

            public function create(): void
            {
            }

            public function drop(): void
            {
            }
        };

        $projection->handledEvents();
    }
}
