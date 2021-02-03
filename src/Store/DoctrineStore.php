<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

abstract class DoctrineStore implements Store
{
    protected Connection $connection;

    public function __construct(Connection $eventConnection)
    {
        $this->connection = $eventConnection;
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    abstract public function schema(): Schema;

    /**
     * @param array{aggregateId: string, playhead: ?int, event: class-string<AggregateChanged>, payload: string, recordedOn: string} $result
     *
     * @return array{aggregateId: string, playhead: ?int, event: class-string<AggregateChanged>, payload: string, recordedOn: DateTimeImmutable}
     */
    protected static function normalizeResult(AbstractPlatform $platform, array $result): array
    {
        $recordedOn = Type::getType(Types::DATETIMETZ_IMMUTABLE)->convertToPHPValue(
            $result['recordedOn'],
            $platform
        );

        if (!$recordedOn instanceof DateTimeImmutable) {
            throw new StoreException('recordedOn should be a DateTimeImmutable object');
        }

        $result['recordedOn'] = $recordedOn;

        return $result;
    }
}
