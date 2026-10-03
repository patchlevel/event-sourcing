<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Projection;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Events\StudentSubscribedToCourse;

final class NumberOfCourseSubscriptions extends BasicProjection
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

    /** @return list<string> */
    protected function tagFilter(): array
    {
        return ["course:{$this->courseId}"];
    }
}
