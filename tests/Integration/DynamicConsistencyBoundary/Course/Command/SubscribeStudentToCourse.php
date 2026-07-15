<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Command;

use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\CourseId;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\StudentId;

final class SubscribeStudentToCourse
{
    public function __construct(
        public readonly StudentId $studentId,
        public readonly CourseId $courseId,
    ) {
    }
}
