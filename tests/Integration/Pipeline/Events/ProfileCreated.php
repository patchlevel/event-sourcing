<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

final class ProfileCreated extends AggregateChanged
{
    public static function raise(string $id): AggregateChanged
    {
        return self::occur($id, ['id' => $id]);
    }

    public function profileId(): string
    {
        return $this->aggregateId;
    }
}
