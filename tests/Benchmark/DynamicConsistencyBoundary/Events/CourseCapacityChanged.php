<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;

#[Event('course.capacity_changed')]
final class CourseCapacityChanged
{
    public function __construct(
        #[EventTag(prefix: 'course')]
        public readonly string $courseId,
        public readonly int $capacity,
    ) {
    }
}
