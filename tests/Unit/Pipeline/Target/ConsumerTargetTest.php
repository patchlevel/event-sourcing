<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Target;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Consumer;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Pipeline\Target\ConsumerTarget;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Pipeline\Target\ConsumerTarget */
final class ConsumerTargetTest extends TestCase
{
    use ProphecyTrait;

    public function testSave(): void
    {
        $message = new Message(
            new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@test.com')),
        );

        $consumer = $this->prophesize(Consumer::class);
        $consumer->consume($message)->shouldBeCalledOnce();

        $consumerTarget = new ConsumerTarget($consumer->reveal());
        $consumerTarget->save($message);
    }

    public function testCreate(): void
    {
        $message = new Message(
            new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@test.com')),
        );

        $listener = new class {
            public int $count = 0;
            #[Subscribe(Subscribe::ALL)]
            public function consumeAll(Message $message): void
            {
                $this->count++;
            }
        };

        $consumerTarget = ConsumerTarget::create([$listener]);
        $consumerTarget->save($message);

        self::assertSame(1, $listener->count);
    }
}
