<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Handler;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DecisionModel\DecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\EventAppender;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Command\ChangeCourseCapacity;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\CourseCapacityChanged;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection\CourseCapacityProjection;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection\CourseExists;
use RuntimeException;

final class ChangeCourseCapacityHandler
{
    public function __construct(
        private readonly DecisionModelBuilder $decisionModelBuilder,
        private readonly EventAppender $eventAppender,
    ) {
    }

    #[Handle]
    public function __invoke(ChangeCourseCapacity $command): void
    {
        $state = $this->decisionModelBuilder->build(
            [
                'courseExists' => new CourseExists($command->courseId),
                'courseCapacity' => new CourseCapacityProjection($command->courseId),
            ],
        );

        if (!$state['courseExists']) {
            throw new RuntimeException('Course does not exist');
        }

        if ($state['courseCapacity'] === $command->capacity) {
            return;
        }

        $this->eventAppender->append([
            new CourseCapacityChanged(
                $command->courseId,
                $command->capacity,
            ),
        ], $state->appendCondition);
    }
}
