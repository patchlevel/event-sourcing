<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Command;

use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\CourseId;

final class DefineCourse
{
    public function __construct(
        public readonly CourseId $courseId,
        public readonly int $capacity,
    ) {
    }
}
