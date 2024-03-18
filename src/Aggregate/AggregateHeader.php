<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Message\Header;

/**
 * @template-implements Header<array{aggregateName: string, aggregateId: string, playhead: int, recordedOn: string}>
 * @psalm-immutable
 */
final class AggregateHeader implements Header
{
    /** @param positive-int $playhead */
    public function __construct(
        public readonly string $aggregateName,
        public readonly string $aggregateId,
        public readonly int $playhead,
        public readonly DateTimeImmutable $recordedOn,
    ) {
    }

    public static function name(): string
    {
        return 'aggregate';
    }

    public static function fromJsonSerialize(array $data): static
    {
        return new self(
            $data['aggregateName'],
            $data['aggregateId'],
            $data['playhead'],
            new DateTimeImmutable($data['recordedOn']),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'aggregateName' => $this->aggregateName,
            'aggregateId' => $this->aggregateId,
            'playhead' => $this->playhead,
            'recordedOn' => $this->recordedOn->format(DateTimeImmutable::ATOM),
        ];
    }
}
