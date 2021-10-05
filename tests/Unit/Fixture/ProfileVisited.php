<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

/**
 * @template-extends AggregateChanged<array{visitorId: string}>
 */
final class ProfileVisited extends AggregateChanged
{
    public static function raise(ProfileId $visitedId, ProfileId $visitorId): self
    {
        return self::occur(
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
