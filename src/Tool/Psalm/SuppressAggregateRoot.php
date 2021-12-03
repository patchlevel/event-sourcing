<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tool\Psalm;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\SnapshotableAggregateRoot;
use Psalm\Plugin\EventHandler\AfterClassLikeVisitInterface;
use Psalm\Plugin\EventHandler\Event\AfterClassLikeVisitEvent;

use function in_array;

class SuppressAggregateRoot implements AfterClassLikeVisitInterface
{
    public static function afterClassLikeVisit(AfterClassLikeVisitEvent $event): void
    {
        $storage = $event->getStorage();

        if (
            !$storage->user_defined
            || $storage->is_interface
            || !in_array($storage->parent_class, [AggregateRoot::class, SnapshotableAggregateRoot::class], true)
        ) {
            return;
        }

        $storage->suppressed_issues[] = 'PropertyNotSetInConstructor';
    }
}
