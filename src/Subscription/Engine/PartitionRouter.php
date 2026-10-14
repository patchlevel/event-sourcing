<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;

use function is_string;
use function sprintf;
use function strlen;

/**
 * The main subscription of a partitioned subscriber never calls the subscriber itself. It resolves the partition key of
 * every message exactly once and writes the message into the inbox of that partition, creating the partition on the way.
 * A partition only processes the messages in its inbox and never resolves the key again, so a changed or
 * non-deterministic key method can not make a message disappear.
 *
 * A router lives for one run of the claimed main subscription, so the known partitions never go stale.
 *
 * @internal
 */
final class PartitionRouter
{
    /** Maximum length of a subscription id in bytes, the id of a partition has to fit into it. */
    public const MAX_ID_LENGTH = 255;

    /** @var array<string, array<string, true>> main subscription id => partition ids */
    private array $knownPartitions = [];

    private bool $routed = false;

    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    /** @param MetadataSubscriberAccessor<object> $subscriber */
    public function route(int $index, Message $message, Subscription $main, MetadataSubscriberAccessor $subscriber): void
    {
        $partitionKey = $this->partitionKey($message, $main, $subscriber);
        $partitionId = Subscription::partitionId($main->id(), $partitionKey);

        $this->knownPartitions[$main->id()] ??= $this->loadPartitions($main);

        if (!isset($this->knownPartitions[$main->id()][$partitionId])) {
            $this->subscriptionManager->add(Subscription::partition($main, $partitionKey, $index - 1));
            $this->knownPartitions[$main->id()][$partitionId] = true;

            $this->logger?->info(sprintf(
                'Subscription Engine: Partition "%s" of subscriber "%s" created.',
                $partitionKey,
                $main->id(),
            ));
        }

        $this->subscriptionManager->addToInbox($partitionId, $index);
        $this->routed = true;
    }

    /** Whether messages were routed, then the partitions have new work. */
    public function hasRouted(): bool
    {
        return $this->routed;
    }

    /** @param MetadataSubscriberAccessor<object> $subscriber */
    private function partitionKey(Message $message, Subscription $main, MetadataSubscriberAccessor $subscriber): string
    {
        $partitionMethod = $subscriber->partitionMethod();

        if ($partitionMethod === null) {
            throw new UnexpectedError(sprintf('The subscriber of "%s" has no partition method.', $main->id()));
        }

        $partitionKey = $partitionMethod($message);

        if (!is_string($partitionKey)) {
            throw InvalidPartitionKey::notAString($main->id(), $partitionKey);
        }

        if ($partitionKey === '') {
            throw InvalidPartitionKey::empty($main->id());
        }

        if (strlen(Subscription::partitionId($main->id(), $partitionKey)) > self::MAX_ID_LENGTH) {
            throw InvalidPartitionKey::tooLong($main->id(), $partitionKey, self::MAX_ID_LENGTH);
        }

        return $partitionKey;
    }

    /** @return array<string, true> */
    private function loadPartitions(Subscription $main): array
    {
        $partitions = [];

        foreach ($this->subscriptionManager->find(new SubscriptionCriteria(ids: [$main->id()], partitions: true)) as $partition) {
            $partitions[$partition->id()] = true;
        }

        return $partitions;
    }
}
