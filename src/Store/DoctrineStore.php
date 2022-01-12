<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function array_key_exists;
use function is_int;

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
     * @param array{aggregateId: string, playhead: string, event: class-string<T>, payload: string, recordedOn: string, recordedon?: string, aggregateid?: string} $result
     *
     * @return array{aggregateId: string, playhead: int, event: class-string<T>, payload: string, recordedOn: DateTimeImmutable}
     *
     * @template T
     */
    final protected static function normalizeResult(AbstractPlatform $platform, array $result): array
    {
        if (array_key_exists('aggregateid', $result) && array_key_exists('recordedon', $result)) {
            $result['aggregateId'] = $result['aggregateid'];
            $result['recordedOn'] = $result['recordedon'];

            unset($result['aggregateid'], $result['recordedon']);
        }

        $result['recordedOn'] = self::normalizeRecordedOn($result['recordedOn'], $platform);
        $result['playhead'] = self::normalizePlayhead($result['playhead'], $platform);

        return $result;
    }

    private static function normalizeRecordedOn(string $recordedOnAsString, AbstractPlatform $platform): DateTimeImmutable
    {
        $recordedOn = Type::getType(Types::DATETIMETZ_IMMUTABLE)->convertToPHPValue($recordedOnAsString, $platform);

        if (!$recordedOn instanceof DateTimeImmutable) {
            throw new InvalidType('recordedOn', 'DateTimeImmutable');
        }

        return $recordedOn;
    }

    private static function normalizePlayhead(string $playheadAsString, AbstractPlatform $platform): int
    {
        $playhead = Type::getType(Types::INTEGER)->convertToPHPValue($playheadAsString, $platform);

        if (!is_int($playhead)) {
            throw new InvalidType('playhead', 'int');
        }

        return $playhead;
    }
}
