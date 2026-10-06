<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Projection;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Events\CourseDefined;

final class CourseExists extends BasicProjection
{
    public function __construct(
        private readonly string $courseId,
    ) {
    }

    public function initialState(): bool
    {
        return false;
    }

    #[Apply]
    public function applyCourseDefined(bool $state, CourseDefined $event): bool
    {
        return true;
    }

    /** @return list<string> */
    protected function tagFilter(): array
    {
        return ["course:{$this->courseId}"];
    }
}
