<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\MessageProcessor;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionRunner;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(SubscriptionRunner::class)]
final class SubscriptionRunnerTest extends TestCase
{
    public function testProcessWithoutSubscriptions(): void
    {
        $runner = $this->runner(
            $this->createMock(MessageLoader::class),
            $this->createMock(SubscriberAccessorRepository::class),
        );

        self::assertEquals(ProcessedResult::empty(), $runner->process([], null));
    }

    public function testProcessSkipsUnknownSubscriber(): void
    {
        $loader = $this->createMock(MessageLoader::class);
        $loader
            ->expects($this->never())
            ->method('load');

        $subscriberRepository = $this->createMock(SubscriberAccessorRepository::class);
        $subscriberRepository
            ->expects($this->atLeastOnce())
            ->method('get')
            ->with('foo')
            ->willReturn(null);

        $runner = $this->runner($loader, $subscriberRepository);

        self::assertEquals(
            ProcessedResult::empty(),
            $runner->process([new Subscription('foo')], null),
        );
    }

    public function testProcessMessages(): void
    {
        $subscription = new Subscription('foo', runMode: RunMode::FromBeginning, status: Status::Active);

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        $loader = $this->createMock(MessageLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->with(null, [$subscription])
            ->willReturn(new Stream([1 => $message, 2 => $message]));

        $subscriberRepository = $this->subscriberRepository();

        $runner = $this->runner($loader, $subscriberRepository);

        $result = $runner->process([$subscription], null);

        self::assertSame(2, $result->processedMessages);
        self::assertTrue($result->finished);
        self::assertSame([], $result->errors);
        self::assertSame(2, $subscription->position());
        self::assertSame(Status::Active, $subscription->status());
    }

    public function testProcessWithLimit(): void
    {
        $subscription = new Subscription('foo', runMode: RunMode::FromBeginning, status: Status::Active);

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        $loader = $this->createMock(MessageLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->with(null, [$subscription])
            ->willReturn(new Stream([1 => $message, 2 => $message]));

        $runner = $this->runner($loader, $this->subscriberRepository());

        $result = $runner->process([$subscription], 1);

        self::assertSame(1, $result->processedMessages);
        self::assertFalse($result->finished);
        self::assertSame(1, $subscription->position());
    }

    public function testProcessFinishesRunOnceSubscription(): void
    {
        $subscription = new Subscription('foo', runMode: RunMode::Once, status: Status::Active);

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        $loader = $this->createMock(MessageLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->willReturn(new Stream([1 => $message]));

        $runner = $this->runner($loader, $this->subscriberRepository());

        $result = $runner->process([$subscription], null);

        self::assertTrue($result->finished);
        self::assertSame(Status::Finished, $subscription->status());
    }

    public function testProcessBootFinishesRunOnceSubscription(): void
    {
        $subscription = new Subscription('foo', runMode: RunMode::Once, status: Status::Booting);

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        $loader = $this->createMock(MessageLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->willReturn(new Stream([1 => $message]));

        $runner = $this->runner($loader, $this->subscriberRepository());

        $runner->process([$subscription], null, true);

        self::assertSame(Status::Finished, $subscription->status());
    }

    public function testProcessBootActivatesSubscription(): void
    {
        $subscription = new Subscription('foo', runMode: RunMode::FromBeginning, status: Status::Booting);

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        $loader = $this->createMock(MessageLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->willReturn(new Stream([1 => $message]));

        $runner = $this->runner($loader, $this->subscriberRepository());

        $runner->process([$subscription], null, true);

        self::assertSame(Status::Active, $subscription->status());
    }

    private function runner(
        MessageLoader $loader,
        SubscriberAccessorRepository $subscriberRepository,
    ): SubscriptionRunner {
        $eventDispatcher = new EventDispatcher();

        return new SubscriptionRunner(
            $loader,
            new SubscriptionManager($this->createMock(SubscriptionStore::class)),
            $subscriberRepository,
            new MessageProcessor($subscriberRepository, $eventDispatcher),
            $eventDispatcher,
        );
    }

    private function subscriberRepository(): SubscriberAccessorRepository
    {
        $accessor = new MetadataSubscriberAccessor(
            new class {
            },
            new SubscriberMetadata('foo'),
        );

        $subscriberRepository = $this->createMock(SubscriberAccessorRepository::class);
        $subscriberRepository
            ->method('get')
            ->with('foo')
            ->willReturn($accessor);

        return $subscriberRepository;
    }
}
