<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Engine\MessageProcessor;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(MessageProcessor::class)]
final class MessageProcessorTest extends TestCase
{
    public function testProcessInvokesSubscribeMethod(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public ProfileVisited|null $event = null;

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(ProfileVisited $event): void
            {
                $this->event = $event;
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new EventDispatcher(),
            new NullLogger(),
        );

        $event = new ProfileVisited(ProfileId::fromString('1'));
        $message = Message::create($event);
        $subscription = new Subscription('test', status: Status::Active);

        $error = $processor->process(1, $message, $subscription);

        self::assertNull($error);
        self::assertSame($event, $subscriber->event);
        self::assertSame(1, $subscription->position());
    }
}
