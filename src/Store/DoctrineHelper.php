<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function is_array;
use function is_int;

final class DoctrineHelper
{
    public static function normalizeRecordedOn(string $recordedOn, AbstractPlatform $platform): DateTimeImmutable
    {
        $normalizedRecordedOn = Type::getType(Types::DATETIMETZ_IMMUTABLE)->convertToPHPValue($recordedOn, $platform);

        if (!$normalizedRecordedOn instanceof DateTimeImmutable) {
            throw new InvalidType('recorded_on', DateTimeImmutable::class);
        }

        return $normalizedRecordedOn;
    }

    /** @return positive-int */
    public static function normalizePlayhead(string|int $playhead, AbstractPlatform $platform): int
    {
        $normalizedPlayhead = Type::getType(Types::INTEGER)->convertToPHPValue($playhead, $platform);

        if (!is_int($normalizedPlayhead) || $normalizedPlayhead <= 0) {
            throw new InvalidType('playhead', 'positive-int');
        }

        return $normalizedPlayhead;
    }

    /** @return array<string, string> */
    public static function normalizeCustomHeaders(string $customHeaders, AbstractPlatform $platform): array
    {
        $normalizedCustomHeaders = Type::getType(Types::JSON)->convertToPHPValue($customHeaders, $platform);

        if (!is_array($normalizedCustomHeaders)) {
            throw new InvalidType('custom_headers', 'array');
        }

        return $normalizedCustomHeaders;
    }
}
