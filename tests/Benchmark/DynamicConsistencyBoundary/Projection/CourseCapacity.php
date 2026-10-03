<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Projection;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Events\CourseCapacityChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Events\CourseDefined;

final class CourseCapacity extends BasicProjection
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
    public function applyCourseDefined(int $state, CourseDefined $event): int
    {
        return $event->capacity;
    }

    #[Apply]
    public function applyCourseCapacityChanged(int $state, CourseCapacityChanged $event): int
    {
        return $event->capacity;
    }

    /** @return list<string> */
    protected function tagFilter(): array
    {
        return ["course:{$this->courseId}"];
    }
}
