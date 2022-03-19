<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\InMemorySource;
use Patchlevel\EventSourcing\Pipeline\Target\InMemoryTarget;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Pipeline\Pipeline  */
class PipelineTest extends TestCase
{
    public function testPipeline(): void
    {
        $messages = [
            new Message(
                Profile::class,
                '1',
                1,
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hallo@patchlevel.de')
                )
            ),
            new Message(
                Profile::class,
                '1',
                2,
                new ProfileVisited(
                    ProfileId::fromString('1')
                )
            ),
            new Message(
                Profile::class,
                '1',
                3,
                new ProfileVisited(
                    ProfileId::fromString('1')
                )
            ),
            new Message(
                Profile::class,
                '1',
                1,
                new ProfileCreated(
                    ProfileId::fromString('2'),
                    Email::fromString('hallo@patchlevel.de')
                )
            ),
            new Message(
                Profile::class,
                '1',
                2,
                new ProfileVisited(
                    ProfileId::fromString('2')
                )
            ),
        ];

        $source = new InMemorySource($messages);
        $target = new InMemoryTarget();
        $pipeline = new Pipeline($source, $target);

        self::assertSame(5, $pipeline->count());

        $pipeline->run();

        self::assertSame($messages, $target->messages());
    }

    public function testPipelineWithMiddleware(): void
    {
        $messages = [
            new Message(
                Profile::class,
                '1',
                1,
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hallo@patchlevel.de')
                )
            ),
            new Message(
                Profile::class,
                '1',
                2,
                new ProfileVisited(
                    ProfileId::fromString('1')
                )
            ),
            new Message(
                Profile::class,
                '1',
                3,
                new ProfileVisited(
                    ProfileId::fromString('1')
                )
            ),
            new Message(
                Profile::class,
                '2',
                1,
                new ProfileCreated(
                    ProfileId::fromString('2'),
                    Email::fromString('hallo@patchlevel.de')
                )
            ),
            new Message(
                Profile::class,
                '2',
                2,
                new ProfileVisited(
                    ProfileId::fromString('2')
                )
            ),
        ];

        $source = new InMemorySource($messages);
        $target = new InMemoryTarget();
        $pipeline = new Pipeline(
            $source,
            $target,
            [
                new ExcludeEventMiddleware([ProfileCreated::class]),
                new RecalculatePlayheadMiddleware(),
            ]
        );

        self::assertSame(5, $pipeline->count());

        $pipeline->run();

        $resultMessages = $target->messages();

        self::assertCount(3, $resultMessages);

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[0]->event());
        self::assertSame('1', $resultMessages[0]->aggregateId());
        self::assertSame(1, $resultMessages[0]->playhead());

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[1]->event());
        self::assertSame('1', $resultMessages[1]->aggregateId());
        self::assertSame(2, $resultMessages[1]->playhead());

        self::assertInstanceOf(ProfileVisited::class, $resultMessages[2]->event());
        self::assertSame('2', $resultMessages[2]->aggregateId());
        self::assertSame(1, $resultMessages[2]->playhead());
    }
}
