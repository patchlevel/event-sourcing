<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\CourseId;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\StudentSubscribedToCourse;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\StudentId;

/** @extends BasicProjection<list<CourseId>> */
final class StudentCoursesProjection extends BasicProjection
{
    public function __construct(
        private readonly StudentId $studentId,
    ) {
    }

    /**
     * @param list<CourseId> $state
     *
     * @return list<CourseId>
     */
    #[Apply]
    public function applyStudentSubscribedToCourse(array $state, StudentSubscribedToCourse $event): array
    {
        return [...$state, $event->courseId];
    }

    /** @return list<CourseId> */
    public function initialState(): array
    {
        return [];
    }

    /** @return list<string> */
    public function tagFilter(): array
    {
        return ["student:{$this->studentId->toString()}"];
    }
}
