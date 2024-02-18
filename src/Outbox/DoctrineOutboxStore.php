<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\EventBus\HeaderNotFound;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Serializer\MessageSerializer;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Store\WrongQueryResult;

use function array_map;
use function is_int;
use function is_string;

final class DoctrineOutboxStore implements OutboxStore, DoctrineSchemaConfigurator
{
    public const HEADER_OUTBOX_IDENTIFIER = 'outboxIdentifier';

    public function __construct(
        private readonly Connection $connection,
        private readonly MessageSerializer $messageSerializer,
        private readonly string $outboxTable = 'outbox',
    ) {
    }

    public function saveOutboxMessage(Message ...$messages): void
    {
        $this->connection->transactional(
            function (Connection $connection) use ($messages): void {
                foreach ($messages as $message) {
                    $connection->insert(
                        $this->outboxTable,
                        [
                            'message' => $this->messageSerializer->serialize($message),
                        ],
                    );
                }
            },
        );
    }

    /** @return list<Message> */
    public function retrieveOutboxMessages(int|null $limit = null): array
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->outboxTable)
            ->setMaxResults($limit)
            ->getSQL();

        /** @var list<array{id: int, message: string}> $result */
        $result = $this->connection->fetchAllAssociative($sql);

        return array_map(
            function (array $data) {
                $message = $this->messageSerializer->deserialize($data['message']);

                return $message->withHeader(new OutboxHeader($data['id']));
            },
            $result,
        );
    }

    public function markOutboxMessageConsumed(Message ...$messages): void
    {
        $this->connection->transactional(
            function (Connection $connection) use ($messages): void {
                foreach ($messages as $message) {
                    $id = $this->extractId($message);
                    $connection->delete($this->outboxTable, ['id' => $id]);
                }
            },
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

        $table->addColumn('id', Types::INTEGER)
            ->setNotnull(true)
            ->setAutoincrement(true);
        $table->addColumn('message', Types::STRING)
            ->setNotnull(true)
            ->setLength(16_000);

        $table->setPrimaryKey(['id']);
    }

    private function extractId(Message $message): int
    {
        try {
            $outboxHeader = $message->header(OutboxHeader::class);
        } catch (HeaderNotFound) {
            throw OutboxHeaderIssue::missingHeader(self::HEADER_OUTBOX_IDENTIFIER);
        }

        return $outboxHeader->id;
    }
}
