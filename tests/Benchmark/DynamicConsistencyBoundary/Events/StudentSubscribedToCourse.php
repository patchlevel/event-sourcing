<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;

#[Event('course.student_subscribed')]
final class StudentSubscribedToCourse
{
    public function __construct(
        #[EventTag(prefix: 'course')]
        public readonly string $courseId,
        #[EventTag(prefix: 'student')]
        public readonly string $studentId,
    ) {
    }
}
