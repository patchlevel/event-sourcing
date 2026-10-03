<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Projection;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Checkpoint;
use Patchlevel\EventSourcing\Projection\BasicProjection;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Events\StudentSubscribedToCourse;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Events\SubscriptionsCounted;

final class NumberOfCourseSubscriptionsWithCheckpoint extends BasicProjection
{
    public function __construct(
        private readonly string $courseId,
    ) {
    }

    public function initialState(): int
    {
        return 0;
    }

    #[Apply]
    public function applyStudentSubscribedToCourse(int $state, StudentSubscribedToCourse $event): int
    {
        return $state + 1;
    }

    #[Apply]
    #[Checkpoint]
    public function applySubscriptionsCounted(int $state, SubscriptionsCounted $event): int
    {
        return $event->subscriptions;
    }

    /** @return list<string> */
    protected function tagFilter(): array
    {
        return ["course:{$this->courseId}"];
    }
}
