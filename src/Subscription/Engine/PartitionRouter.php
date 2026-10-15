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

    /** After that many lookups of single partitions, all partitions of the subscriber are loaded at once. */
    private const SINGLE_LOOKUPS = 50;

    /** @var array<string, true> partition ids known to exist */
    private array $knownPartitions = [];

    /** @var array<string, int> main subscription id => single lookups so far */
    private array $lookups = [];

    /** @var array<string, true> main subscriptions whose partitions are all loaded */
    private array $loaded = [];

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

        if (!$this->exists($main, $partitionId)) {
            $this->subscriptionManager->add(Subscription::partition($main, $partitionKey, $index - 1));
            $this->knownPartitions[$partitionId] = true;

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

    /**
     * Looks up only the partitions the routed messages belong to, so routing a few messages stays cheap no matter how
     * many partitions the subscriber has. When many messages are routed, e.g. while catching up, all partitions are
     * loaded with one query instead.
     */
    private function exists(Subscription $main, string $partitionId): bool
    {
        if (isset($this->knownPartitions[$partitionId])) {
            return true;
        }

        if (isset($this->loaded[$main->id()])) {
            return false;
        }

        $this->lookups[$main->id()] = ($this->lookups[$main->id()] ?? 0) + 1;

        if ($this->lookups[$main->id()] > self::SINGLE_LOOKUPS) {
            foreach ($this->subscriptionManager->findLazy(new SubscriptionCriteria(ids: [$main->id()], partitions: true)) as $partition) {
                $this->knownPartitions[$partition->id()] = true;
            }

            $this->loaded[$main->id()] = true;

            return isset($this->knownPartitions[$partitionId]);
        }

        if ($this->subscriptionManager->findLazy(new SubscriptionCriteria(ids: [$partitionId], partitions: true)) === []) {
            return false;
        }

        return $this->knownPartitions[$partitionId] = true;
    }
}
