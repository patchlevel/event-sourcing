<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Handler;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DecisionModel\Decision;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Command\ChangeCourseCapacity;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\CourseCapacityChanged;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection\CourseCapacityProjection;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection\CourseExists;
use RuntimeException;

final class ChangeCourseCapacityHandler
{
    #[Handle]
    public function __invoke(ChangeCourseCapacity $command, Decision $decision): void
    {
        if (!$decision->read(new CourseExists($command->courseId))) {
            throw new RuntimeException('Course does not exist');
        }

        if ($decision->read(new CourseCapacityProjection($command->courseId)) === $command->capacity) {
            return;
        }

        $decision->append([
            new CourseCapacityChanged(
                $command->courseId,
                $command->capacity,
            ),
        ]);
    }
}
