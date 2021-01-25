<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

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
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    protected static function normalizeResult(AbstractPlatform $platform, array $result): array
    {
        if (!$result['recordedOn']) {
            return $result;
        }

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
