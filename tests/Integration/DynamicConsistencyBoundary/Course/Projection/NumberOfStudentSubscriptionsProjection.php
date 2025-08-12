<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\DCB\EventRouter;
use Patchlevel\EventSourcing\DCB\Projection;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\StudentSubscribedToCourse;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\StudentId;

final class NumberOfStudentSubscriptionsProjection implements Projection
{
    use EventRouter;

    public function __construct(
        private readonly StudentId $studentId,
    ) {
    }

    #[Apply]
    public function applyStudentSubscribedToCourse(int $state, StudentSubscribedToCourse $event): int
    {
        return $state + 1;
    }

    public function initialState(): int
    {
        return 0;
    }

    /** @return list<string> */
    public function tagFilter(): array
    {
        return ["student:{$this->studentId->toString()}"];
    }
}
