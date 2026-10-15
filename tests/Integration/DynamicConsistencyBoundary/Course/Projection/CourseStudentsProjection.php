<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\CourseId;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\StudentSubscribedToCourse;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\StudentId;

/** @extends BasicProjection<list<StudentId>> */
final class CourseStudentsProjection extends BasicProjection
{
    public function __construct(
        private readonly CourseId $courseId,
    ) {
    }

    /**
     * @param list<StudentId> $state
     *
     * @return list<StudentId>
     */
    #[Apply]
    public function applyStudentSubscribedToCourse(array $state, StudentSubscribedToCourse $event): array
    {
        return [...$state, $event->studentId];
    }

    /** @return list<StudentId> */
    public function initialState(): array
    {
        return [];
    }

    /** @return list<string> */
    public function tagFilter(): array
    {
        return ["course:{$this->courseId->toString()}"];
    }
}
