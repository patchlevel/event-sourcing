<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\DCB\ApplyTrait;
use Patchlevel\EventSourcing\DCB\Projection;
use Patchlevel\EventSourcing\Store\SubQuery;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\CourseId;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\CourseDefined;

/** @implements Projection<bool> */
final class CourseExists implements Projection
{
    use ApplyTrait;

    public function __construct(
        private readonly CourseId $courseId,
    ) {
    }

    #[Apply]
    public function applyCourseDefined(bool $state, CourseDefined $event): bool
    {
        return true;
    }

    /** @return list<string> */
    public function tagFilter(): array
    {
        return ["course:{$this->courseId->toString()}"];
    }

    public function initialState(): bool
    {
        return false;
    }
}
