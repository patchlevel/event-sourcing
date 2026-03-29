<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\StatefulSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Schema\DoctrineHelper;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\Hydrator\HydratorWithContext;
use Patchlevel\Hydrator\MetadataHydrator;

use function json_decode;
use function json_encode;

final readonly class DoctrineStatefulSubscriberStore implements StatefulSubscriberStore, DoctrineSchemaConfigurator
{
    public function __construct(
        private Connection $connection,
        private HydratorWithContext $hydrator = new MetadataHydrator(),
        private SubscriberMetadataFactory $subscriberMetadataFactory = new AttributeSubscriberMetadataFactory(),
        private string $tableName = 'stateful_subscriber_state',
    ) {
    }

    public function store(StatefulSubscriber $subscriber): void
    {
        $subscriberId = $this->subscriberId($subscriber);
        $data = $this->hydrator->extract($subscriber);

        $this->connection->insert(
            $this->tableName,
            [
                'id' => $subscriberId,
                'state' => json_encode($data),
            ],
        );
    }

    public function load(StatefulSubscriber $subscriber): void
    {
        $subscriberId = $this->subscriberId($subscriber);

        $data = $this->connection->fetchAssociative(
            'SELECT * FROM ' . $this->tableName . ' WHERE id = ?',
            [$subscriberId],
        );

        if (!$data) {
            return;
        }

        $this->hydrator->hydrate(
            $subscriber::class,
            json_decode($data['state'], true),
            [HydratorWithContext::OBJECT_TO_POPULATE => $subscriber],
        );
    }

    public function configureSchema(Schema $schema, Connection $connection): void
    {
        if (!DoctrineHelper::sameDatabase($this->connection, $connection)) {
            return;
        }

        $table = $schema->createTable($this->tableName);

        $table->addColumn('id', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('state', Types::JSON)
            ->setNotnull(true);

        $table->setPrimaryKey(['id']);
    }

    public function subscriberId(StatefulSubscriber $projection): string
    {
        return $this->subscriberMetadataFactory->metadata($projection::class)->id;
    }
}
