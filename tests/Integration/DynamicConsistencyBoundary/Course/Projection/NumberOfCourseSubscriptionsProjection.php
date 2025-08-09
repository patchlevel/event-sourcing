<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\DCB\ApplyTrait;
use Patchlevel\EventSourcing\DCB\Projection;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\CourseId;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\StudentSubscribedToCourse;

final class NumberOfCourseSubscriptionsProjection implements Projection
{
    use ApplyTrait;

    public function __construct(
        private readonly CourseId $courseId,
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
        return ["course:{$this->courseId->toString()}"];
    }
}
