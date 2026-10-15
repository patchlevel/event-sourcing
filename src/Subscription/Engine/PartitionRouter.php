<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;

use function array_chunk;
use function array_keys;
use function sprintf;
use function strlen;

/**
 * The main subscription of a partitioned subscriber never calls the subscriber itself. It resolves the partition key of
 * every message exactly once and writes the message into the inbox of that partition, creating the partition on the way.
 * A partition only processes the messages in its inbox and never resolves the key again, so a changed or
 * non-deterministic partitioner can not make a message disappear.
 *
 * A router lives for one run of the claimed main subscription, so the known partitions never go stale.
 * The partitions of new keys are created by createPartitions() at the end of the run.
 *
 * @internal
 */
final class PartitionRouter
{
    /** Maximum length of a subscription id in bytes, the id of a partition has to fit into it. */
    public const MAX_ID_LENGTH = 255;

    /** How many partition ids are looked up with one query. */
    private const LOOKUP_CHUNK = 500;

    /** @var array<string, true> partition ids known to exist */
    private array $knownPartitions = [];

    /** @var array<string, array{Subscription, string, int}> partition id => main subscription, key and first routed index */
    private array $unknownPartitions = [];

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

        // fails first if the store has no inbox, so no partition is created that could never be processed
        $this->subscriptionManager->addToInbox($partitionId, $index);

        if (isset($this->knownPartitions[$partitionId]) || isset($this->unknownPartitions[$partitionId])) {
            return;
        }

        $this->unknownPartitions[$partitionId] = [$main, $partitionKey, $index];
    }

    /**
     * Creates the partitions of the routed messages that do not exist yet. The partitions are looked up together
     * with one query per chunk, so routing stays cheap no matter how many partitions the subscriber has.
     * Has to be called before the routed messages are flushed, otherwise their inbox has no partition.
     */
    public function createPartitions(): void
    {
        foreach (array_chunk($this->unknownPartitions, self::LOOKUP_CHUNK, true) as $chunk) {
            $existing = $this->subscriptionManager->find(
                new SubscriptionCriteria(ids: array_keys($chunk), partitions: true),
            );

            foreach ($existing as $partition) {
                $this->knownPartitions[$partition->id()] = true;
            }

            foreach ($chunk as $partitionId => [$main, $partitionKey, $firstIndex]) {
                if (isset($this->knownPartitions[$partitionId])) {
                    continue;
                }

                $this->subscriptionManager->add(Subscription::partition($main, $partitionKey, $firstIndex - 1));
                $this->knownPartitions[$partitionId] = true;

                $this->logger?->info(sprintf(
                    'Subscription Engine: Partition "%s" of subscriber "%s" created.',
                    $partitionKey,
                    $main->id(),
                ));
            }
        }

        $this->unknownPartitions = [];
    }

    /** @param MetadataSubscriberAccessor<object> $subscriber */
    private function partitionKey(Message $message, Subscription $main, MetadataSubscriberAccessor $subscriber): string
    {
        $partitioner = $subscriber->partitioner();

        if ($partitioner === null) {
            throw new UnexpectedError(sprintf('The subscriber of "%s" has no partitioner.', $main->id()));
        }

        $partitionKey = $partitioner($message);

        if ($partitionKey === '') {
            throw InvalidPartitionKey::empty($main->id());
        }

        if (strlen(Subscription::partitionId($main->id(), $partitionKey)) > self::MAX_ID_LENGTH) {
            throw InvalidPartitionKey::tooLong($main->id(), $partitionKey, self::MAX_ID_LENGTH);
        }

        return $partitionKey;
    }
}
