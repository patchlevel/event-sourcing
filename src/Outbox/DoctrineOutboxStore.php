<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Schema\SchemaConfigurator;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Store\DoctrineHelper;
use Patchlevel\EventSourcing\Store\WrongQueryResult;

use function array_map;
use function is_int;
use function is_string;

final class DoctrineOutboxStore implements OutboxStore, SchemaConfigurator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EventSerializer $serializer,
        private readonly AggregateRootRegistry $aggregateRootRegistry,
        private readonly string $outboxTable = 'outbox'
    ) {
    }

    public function saveOutboxMessage(Message ...$messages): void
    {
        $this->connection->transactional(
            function (Connection $connection) use ($messages): void {
                foreach ($messages as $message) {
                    $event = $message->event();

                    $data = $this->serializer->serialize($event);

                    $connection->insert(
                        $this->outboxTable,
                        [
                            'aggregate' => $this->aggregateRootRegistry->aggregateName($message->aggregateClass()),
                            'aggregate_id' => $message->aggregateId(),
                            'playhead' => $message->playhead(),
                            'event' => $data->name,
                            'payload' => $data->payload,
                            'recorded_on' => $message->recordedOn(),
                            'custom_headers' => $message->customHeaders(),
                        ],
                        [
                            'recorded_on' => Types::DATETIMETZ_IMMUTABLE,
                            'custom_headers' => Types::JSON,
                        ]
                    );
                }
            }
        );
    }

    /**
     * @return list<Message>
     */
    public function retrieveOutboxMessages(?int $limit = null): array
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->outboxTable)
            ->setMaxResults($limit)
            ->getSQL();

        /** @var list<array{aggregate: string, aggregate_id: string, playhead: string|int, event: string, payload: string, recorded_on: string, custom_headers: string}> $result */
        $result = $this->connection->fetchAllAssociative($sql);
        $platform = $this->connection->getDatabasePlatform();

        return array_map(
            function (array $data) use ($platform) {
                $event = $this->serializer->deserialize(new SerializedEvent($data['event'], $data['payload']));

                return Message::create($event)
                    ->withAggregateClass($this->aggregateRootRegistry->aggregateClass($data['aggregate']))
                    ->withAggregateId($data['aggregate_id'])
                    ->withPlayhead(DoctrineHelper::normalizePlayhead($data['playhead'], $platform))
                    ->withRecordedOn(DoctrineHelper::normalizeRecordedOn($data['recorded_on'], $platform))
                    ->withCustomHeaders(DoctrineHelper::normalizeCustomHeaders($data['custom_headers'], $platform));
            },
            $result
        );
    }

    public function markOutboxMessageConsumed(Message ...$messages): void
    {
        $this->connection->transactional(
            function (Connection $connection) use ($messages): void {
                foreach ($messages as $message) {
                    $connection->delete(
                        $this->outboxTable,
                        [
                            'aggregate' => $this->aggregateRootRegistry->aggregateName($message->aggregateClass()),
                            'aggregate_id' => $message->aggregateId(),
                            'playhead' => $message->playhead(),
                        ]
                    );
                }
            }
        );
    }

    public function countOutboxMessages(): int
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->outboxTable)
            ->getSQL();

        $result = $this->connection->fetchOne($sql);

        if (!is_int($result) && !is_string($result)) {
            throw new WrongQueryResult();
        }

        return (int)$result;
    }

    public function configureSchema(Schema $schema, Connection $connection): void
    {
        $table = $schema->createTable($this->outboxTable);

        $table->addColumn('aggregate', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('aggregate_id', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('playhead', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('event', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('payload', Types::JSON)
            ->setNotnull(true);
        $table->addColumn('recorded_on', Types::DATETIMETZ_IMMUTABLE)
            ->setNotnull(false);
        $table->addColumn('custom_headers', Types::JSON)
            ->setNotnull(true);

        $table->setPrimaryKey(['aggregate', 'aggregate_id', 'playhead']);
    }
}
