<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Context\MessageContext;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\CorrelationIdHeader;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessage;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageSuccess;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\MessageContextSubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\MessageProcessor;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_keys;

#[CoversClass(MessageContextSubscriber::class)]
final class MessageContextSubscriberTest extends TestCase
{
    public function testContextIsNotStrandedWhenTerminalListenerFails(): void
    {
        $context = new MessageContext();

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new MessageContextSubscriber($context));

        // a batch flush runs on the terminal event and can fail
        $eventDispatcher->addListener(
            OnHandleMessageSuccess::class,
            static fn (): never => throw new RuntimeException('FLUSH FAILED'),
        );

        $error = $this->processor($eventDispatcher)->process(
            1,
            $this->message(),
            new Subscription('test'),
        );

        self::assertNotNull($error);

        // the failed message must not contaminate whatever is recorded next
        self::assertNull($context->causationId());
        self::assertNull($context->correlationId());
    }

    public function testContextIsPoppedBeforeOtherTerminalListeners(): void
    {
        $context = new MessageContext();

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new MessageContextSubscriber($context));

        $causationIdDuringFlush = 'not called';

        // a batch flush covers many messages, so it must not be attributed to the one
        // which happened to trip the threshold
        $eventDispatcher->addListener(
            OnHandleMessageSuccess::class,
            static function () use ($context, &$causationIdDuringFlush): void {
                $causationIdDuringFlush = $context->causationId();
            },
        );

        $this->processor($eventDispatcher)->process(1, $this->message(), new Subscription('test'));

        self::assertNull($causationIdDuringFlush);
    }

    private function processor(EventDispatcher $eventDispatcher): MessageProcessor
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(ProfileVisited $event): void
            {
            }
        };

        return new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            $eventDispatcher,
            [],
            new NullLogger(),
        );
    }

    public function testPushesAndPopsAroundMessage(): void
    {
        $context = new MessageContext();
        $listener = new MessageContextSubscriber($context);

        $subscription = new Subscription('test');
        $message = $this->message();

        $listener->onHandleMessage(new OnHandleMessage($subscription, $message));

        self::assertSame('event-1', $context->causationId());
        self::assertSame('correlation-1', $context->correlationId());

        $listener->onHandleMessageSuccess(new OnHandleMessageSuccess($subscription, $message, 1));

        self::assertNull($context->causationId());
        self::assertNull($context->correlationId());
    }

    public function testPopsOnError(): void
    {
        $context = new MessageContext();
        $listener = new MessageContextSubscriber($context);

        $subscription = new Subscription('test');
        $message = $this->message();

        $listener->onHandleMessage(new OnHandleMessage($subscription, $message));
        $listener->onHandleMessageError(new OnHandleMessageError(
            $subscription,
            new RuntimeException('ERROR'),
            $message,
            1,
        ));

        self::assertNull($context->causationId());
    }

    public function testSuccessWithoutHandleMessageDoesNotPop(): void
    {
        $context = new MessageContext();
        $context->push('outer-causation', 'outer-correlation');

        $listener = new MessageContextSubscriber($context);

        // a subscriber without a matching subscribe method is reported as
        // success without a preceding OnHandleMessage
        $listener->onHandleMessageSuccess(new OnHandleMessageSuccess(
            new Subscription('test'),
            $this->message(),
            1,
        ));

        self::assertSame('outer-causation', $context->causationId());
        self::assertSame('outer-correlation', $context->correlationId());
    }

    public function testDoubleSuccessPopsOnlyOnce(): void
    {
        $context = new MessageContext();
        $context->push('outer-causation', 'outer-correlation');

        $listener = new MessageContextSubscriber($context);

        $subscription = new Subscription('test');
        $message = $this->message();

        $listener->onHandleMessage(new OnHandleMessage($subscription, $message));
        $listener->onHandleMessageSuccess(new OnHandleMessageSuccess($subscription, $message, 1));
        $listener->onHandleMessageSuccess(new OnHandleMessageSuccess($subscription, $message, 2));

        self::assertSame('outer-causation', $context->causationId());
        self::assertSame('outer-correlation', $context->correlationId());
    }

    public function testCommandKeepsFramesOfEnclosingScopes(): void
    {
        $context = new MessageContext();

        // the command bus seeded a correlation id for the command it is handling
        $context->push('outer-causation', 'outer-correlation');

        $listener = new MessageContextSubscriber($context);

        // saving an aggregate can run the engine synchronously, which dispatches OnCommand
        // inside the scope of the command bus
        $listener->onCommand(new OnCommand(new Run()));

        self::assertSame('outer-causation', $context->causationId());
        self::assertSame('outer-correlation', $context->correlationId());
    }

    public function testCommandRemovesOnlyItsOwnFrame(): void
    {
        $context = new MessageContext();
        $context->push('outer-causation', 'outer-correlation');

        $listener = new MessageContextSubscriber($context);

        $listener->onHandleMessage(new OnHandleMessage(new Subscription('test'), $this->message()));
        $listener->onCommand(new OnCommand(new Run()));

        self::assertSame('outer-causation', $context->causationId());
        self::assertSame('outer-correlation', $context->correlationId());

        // the healed frame must not be popped a second time
        $listener->onHandleMessageSuccess(new OnHandleMessageSuccess(
            new Subscription('test'),
            $this->message(),
            1,
        ));

        self::assertSame('outer-causation', $context->causationId());
    }

    public function testSubscribedEvents(): void
    {
        self::assertSame(
            [
                OnCommand::class,
                OnHandleMessage::class,
                OnHandleMessageSuccess::class,
                OnHandleMessageError::class,
            ],
            array_keys(MessageContextSubscriber::getSubscribedEvents()),
        );
    }

    private function message(): Message
    {
        return Message::create(new ProfileVisited(ProfileId::fromString('test')))
            ->withHeader(new EventIdHeader('event-1'))
            ->withHeader(new CorrelationIdHeader('correlation-1'));
    }
}
