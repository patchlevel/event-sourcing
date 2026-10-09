<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\CourseId;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\StudentId;

#[Event('course.student_subscribed')]
final class StudentSubscribedToCourse
{
    public function __construct(
        #[EventTag(prefix: 'student')]
        public readonly StudentId $studentId,
        #[EventTag(prefix: 'course')]
        public readonly CourseId $courseId,
    ) {
    }
}
