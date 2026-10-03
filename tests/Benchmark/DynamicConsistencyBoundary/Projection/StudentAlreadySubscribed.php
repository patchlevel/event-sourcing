<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Projection;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Events\StudentSubscribedToCourse;

final class StudentAlreadySubscribed extends BasicProjection
{
    public function __construct(
        private readonly string $courseId,
        private readonly string $studentId,
    ) {
    }

    public function initialState(): bool
    {
        return false;
    }

    #[Apply]
    public function applyStudentSubscribedToCourse(bool $state, StudentSubscribedToCourse $event): bool
    {
        return true;
    }

    /** @return list<string> */
    protected function tagFilter(): array
    {
        return ["course:{$this->courseId}", "student:{$this->studentId}"];
    }
}
