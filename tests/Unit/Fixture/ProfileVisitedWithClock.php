<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\ClockRecordDate;

/**
 * @template-extends AggregateChanged<array{visitorId: string}>
 */
final class ProfileVisitedWithClock extends AggregateChanged
{
    use ClockRecordDate;

    public static function raise(ProfileId $visitedId, ProfileId $visitorId): self
    {
        return new self(
            $visitedId->toString(),
            [
                'visitorId' => $visitorId->toString(),
            ]
        );
    }

    public function visitedId(): ProfileId
    {
        return ProfileId::fromString($this->aggregateId);
    }

    public function visitorId(): ProfileId
    {
        return ProfileId::fromString($this->payload['visitorId']);
    }
}
