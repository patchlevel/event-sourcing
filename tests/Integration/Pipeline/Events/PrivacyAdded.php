<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

/**
 * @template-extends AggregateChanged<array{id: string}>
 */
final class PrivacyAdded extends AggregateChanged
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
