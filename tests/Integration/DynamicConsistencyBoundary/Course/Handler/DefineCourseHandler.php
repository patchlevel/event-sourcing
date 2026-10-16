<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Handler;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DecisionModel\Decision;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Command\DefineCourse;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\CourseDefined;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection\CourseExists;
use RuntimeException;

final class DefineCourseHandler
{
    #[Handle]
    public function __invoke(DefineCourse $command, Decision $decision): void
    {
        if ($decision->read(new CourseExists($command->courseId))) {
            throw new RuntimeException('Course already exists');
        }

        $decision->append([
            new CourseDefined(
                $command->courseId,
                $command->capacity,
            ),
        ]);
    }
}
