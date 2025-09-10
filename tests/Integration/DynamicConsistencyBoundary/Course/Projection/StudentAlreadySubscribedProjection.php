<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\CourseId;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\StudentSubscribedToCourse;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\StudentId;

final class StudentAlreadySubscribedProjection extends BasicProjection
{
    public function __construct(
        private readonly StudentId $studentId,
        private readonly CourseId $courseId,
    ) {
    }

    #[Apply]
    public function applyStudentSubscribedToCourse(bool $state, StudentSubscribedToCourse $event): bool
    {
        return true;
    }

    public function initialState(): bool
    {
        return false;
    }

    /** @return list<string> */
    public function tagFilter(): array
    {
        return [
            "student:{$this->studentId->toString()}",
            "course:{$this->courseId->toString()}",
        ];
    }
}
