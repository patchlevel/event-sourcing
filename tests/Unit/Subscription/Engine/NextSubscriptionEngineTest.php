<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Subscription\Engine\AlreadyProcessing;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(DefaultSubscriptionEngine::class)]
final class NextSubscriptionEngineTest extends TestCase
{
    public function testAlreadyProcessingOnBoot(): void
    {
        $subscriptionId = 'test';

        $engine = null;

        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public DefaultSubscriptionEngine|null $engine = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(): void
            {
                $this->engine?->run(new Boot());
            }
        };

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting),
        ]);

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(
            new Stream([1 => new Message(new ProfileVisited(ProfileId::fromString('test')))]),
        );

        $engine = new DefaultSubscriptionEngine(
            $messageLoader,
            $store,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $subscriber->engine = $engine;

        $result = $engine->run(new Boot());

        self::assertCount(1, $result->errors);
        self::assertInstanceOf(AlreadyProcessing::class, $result->errors[0]->throwable);
    }

    public function testAlreadyProcessingOnRun(): void
    {
        $subscriptionId = 'test';

        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public DefaultSubscriptionEngine|null $engine = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(): void
            {
                $this->engine?->run(new Run());
            }
        };

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active),
        ]);

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(
            new Stream([1 => new Message(new ProfileVisited(ProfileId::fromString('test')))]),
        );

        $engine = new DefaultSubscriptionEngine(
            $messageLoader,
            $store,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $subscriber->engine = $engine;

        $result = $engine->run(new Run());

        self::assertCount(1, $result->errors);
        self::assertInstanceOf(AlreadyProcessing::class, $result->errors[0]->throwable);
    }
}
