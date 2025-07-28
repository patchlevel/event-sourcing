<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\CourseId;

#[Event('course.capacity_changed')]
final class CourseCapacityChanged
{
    public function __construct(
        #[EventTag(prefix: 'course')]
        public CourseId $courseId,
        public int $capacity,
    ) {
    }
}
