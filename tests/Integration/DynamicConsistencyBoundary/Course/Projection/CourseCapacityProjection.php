<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\DCB\ApplyTrait;
use Patchlevel\EventSourcing\DCB\Projection;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\CourseId;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\CourseCapacityChanged;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\CourseDefined;

final class CourseCapacityProjection implements Projection
{
    use ApplyTrait;

    public function __construct(
        private readonly CourseId $courseId,
    ) {
    }

    public function initialState(): int
    {
        return 0;
    }

    /** @return list<string> */
    public function tagFilter(): array
    {
        return ["course:{$this->courseId->toString()}"];
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
}
