<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscribeMethodMetadata;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnBeforeHandleMessage;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessage;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageSuccess;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolverContext;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\EventArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\MessageArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\PartitionKeyArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\RecordedOnArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\NoSuitableResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function array_merge;
use function array_values;
use function is_array;
use function iterator_to_array;
use function sprintf;

/** @internal */
final class MessageProcessor
{
    /** @var list<ArgumentResolver> */
    private readonly array $argumentResolvers;

    /** @var array<string, array<class-string, array<string, list<ArgumentResolver>>>> */
    private array $resolverCache = [];

    /** @param iterable<ArgumentResolver>|list<ArgumentResolver> $argumentResolvers */
    public function __construct(
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        iterable $argumentResolvers = [],
        private readonly LoggerInterface|null $logger = null,
    ) {
        $this->argumentResolvers = array_merge(
            // the check for array is required before PHP 8.2
            array_values(is_array($argumentResolvers) ? $argumentResolvers : iterator_to_array($argumentResolvers)),
            [
                new PartitionKeyArgumentResolver(),
                new MessageArgumentResolver(),
                new EventArgumentResolver(),
                new RecordedOnArgumentResolver(),
            ],
        );
    }

    /**
     * The router is required for the main subscription of a partitioned subscriber, which routes the messages into the
     * inboxes of its partitions instead of calling the subscriber.
     */
    public function process(
        int $index,
        Message $message,
        Subscription $subscription,
        PartitionRouter|null $router = null,
    ): Error|ProcessingStopped|null {
        $subscriber = $this->subscriberRepository->get($subscription->subscriberId());

        if (!$subscriber) {
            throw SubscriberNotFound::forSubscriptionId($subscription->id());
        }

        $subscribeMethods = $subscriber->subscribeMethods($message->event()::class);

        if ($subscribeMethods === []) {
            $this->logger?->debug(
                sprintf(
                    'Subscription Engine: Subscriber "%s" for "%s" has no subscribe methods for "%s", continue.',
                    $subscriber::class,
                    $subscription->id(),
                    $message->event()::class,
                ),
            );

            return $this->success($index, $message, $subscription, $subscriber);
        }

        if (!$subscription->isPartition() && $subscriber->metadata()->partition !== null) {
            if ($router === null) {
                throw new UnexpectedError(sprintf('The partitioned subscription "%s" needs a router.', $subscription->id()));
            }

            try {
                $router->route($index, $message, $subscription, $subscriber);
            } catch (Throwable $e) {
                return $this->error($index, $message, $subscription, $subscriber, $e);
            }

            return $this->success($index, $message, $subscription, $subscriber);
        }

        try {
            // decided before OnHandleMessage, which is always followed by a success or an error
            $before = new OnBeforeHandleMessage($subscription, $message);
            $this->eventDispatcher->dispatch($before);

            if ($before->stop) {
                $this->logger?->debug(sprintf(
                    'Subscription Engine: Subscription "%s" stopped before the event "%s".',
                    $subscription->id(),
                    $message->event()::class,
                ));

                return new ProcessingStopped();
            }

            $this->eventDispatcher->dispatch(new OnHandleMessage($subscription, $message));

            $context = new ArgumentResolverContext($message, $subscription, $subscriber->metadata());

            foreach ($subscribeMethods as $subscribeMethod) {
                $arguments = $this->resolveArguments(
                    $subscription->subscriberId(),
                    $message->event()::class,
                    $subscriber->subscriber()::class,
                    $subscribeMethod,
                    $context,
                );

                $subscriber->subscriber()->{$subscribeMethod->name}(...$arguments);
            }
        } catch (Throwable $e) {
            return $this->error($index, $message, $subscription, $subscriber, $e);
        }

        $error = $this->success($index, $message, $subscription, $subscriber);

        if ($error !== null) {
            return $error;
        }

        $this->logger?->debug(
            sprintf(
                'Subscription Engine: Subscriber "%s" for "%s" processed the event "%s".',
                $subscriber::class,
                $subscription->id(),
                $message->event()::class,
            ),
        );

        return null;
    }

    /**
     * A listener can fail as well, e.g. a batch that is flushed after this message. That is a failure of this
     * subscription, not of the others processed together with it.
     *
     * @param MetadataSubscriberAccessor<object> $subscriber
     */
    private function success(
        int $index,
        Message $message,
        Subscription $subscription,
        MetadataSubscriberAccessor $subscriber,
    ): Error|null {
        $event = new OnHandleMessageSuccess($subscription, $message, $index);

        try {
            $this->eventDispatcher->dispatch($event);
        } catch (Throwable $e) {
            return $this->error($index, $message, $subscription, $subscriber, $e);
        }

        if (!$event->shouldChangePosition) {
            return null;
        }

        $subscription->changePosition($index);

        return null;
    }

    /** @param MetadataSubscriberAccessor<object> $subscriber */
    private function error(
        int $index,
        Message $message,
        Subscription $subscription,
        MetadataSubscriberAccessor $subscriber,
        Throwable $throwable,
    ): Error {
        $this->logger?->error(
            sprintf(
                'Subscription Engine: Subscriber "%s" for "%s" could not process the event "%s": %s',
                $subscriber::class,
                $subscription->id(),
                $message->event()::class,
                $throwable->getMessage(),
            ),
        );

        $this->eventDispatcher->dispatch(
            new OnHandleMessageError(
                $subscription,
                $throwable,
                $message,
                $index,
            ),
        );

        return new Error(
            $subscription->id(),
            $throwable->getMessage(),
            $throwable,
        );
    }

    /**
     * @param class-string $eventClass
     * @param class-string $subscriberClass
     *
     * @return list<mixed>
     */
    private function resolveArguments(
        string $subscriberId,
        string $eventClass,
        string $subscriberClass,
        SubscribeMethodMetadata $method,
        ArgumentResolverContext $context,
    ): array {
        $resolvers = $this->resolversFor($subscriberId, $eventClass, $subscriberClass, $method);

        $arguments = [];

        foreach ($method->arguments as $position => $argument) {
            $arguments[] = $resolvers[$position]->resolve($argument, $context);
        }

        return $arguments;
    }

    /**
     * @param class-string $eventClass
     * @param class-string $subscriberClass
     *
     * @return list<ArgumentResolver>
     */
    private function resolversFor(
        string $subscriberId,
        string $eventClass,
        string $subscriberClass,
        SubscribeMethodMetadata $method,
    ): array {
        if (isset($this->resolverCache[$subscriberId][$eventClass][$method->name])) {
            return $this->resolverCache[$subscriberId][$eventClass][$method->name];
        }

        $resolvers = [];

        foreach ($method->arguments as $argument) {
            $resolvers[] = $this->resolverFor($argument, $eventClass, $subscriberClass, $method);
        }

        return $this->resolverCache[$subscriberId][$eventClass][$method->name] = $resolvers;
    }

    /**
     * @param class-string $eventClass
     * @param class-string $subscriberClass
     */
    private function resolverFor(
        ArgumentMetadata $argument,
        string $eventClass,
        string $subscriberClass,
        SubscribeMethodMetadata $method,
    ): ArgumentResolver {
        foreach ($this->argumentResolvers as $resolver) {
            if ($resolver->support($argument, $eventClass)) {
                return $resolver;
            }
        }

        throw new NoSuitableResolver($subscriberClass, $method->name, $argument->name);
    }
}
