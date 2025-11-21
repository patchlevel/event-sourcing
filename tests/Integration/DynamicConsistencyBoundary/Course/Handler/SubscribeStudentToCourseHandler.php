<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Handler;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DecisionModel\DecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\EventAppender;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Command\SubscribeStudentToCourse;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\StudentSubscribedToCourse;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection\CourseCapacityProjection;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection\CourseExists;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection\NumberOfCourseSubscriptionsProjection;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection\NumberOfStudentSubscriptionsProjection;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection\StudentAlreadySubscribedProjection;
use RuntimeException;

final class SubscribeStudentToCourseHandler
{
    public function __construct(
        private readonly DecisionModelBuilder $decisionModelBuilder,
        private readonly EventAppender $eventAppender,
    ) {
    }

    #[Handle]
    public function __invoke(SubscribeStudentToCourse $command): void
    {
        $state = $this->decisionModelBuilder->build(
            projections: [
                'courseExists' => new CourseExists($command->courseId),
                'courseCapacity' => new CourseCapacityProjection($command->courseId),
                'numberOfCourseSubscriptions' => new NumberOfCourseSubscriptionsProjection($command->courseId),
                'numberOfStudentSubscriptions' => new NumberOfStudentSubscriptionsProjection($command->studentId),
                'studentAlreadySubscribed' => new StudentAlreadySubscribedProjection(
                    $command->studentId,
                    $command->courseId,
                ),
            ],
        );

        if (!$state['courseExists']) {
            throw new RuntimeException("Course {$command->courseId->toString()} does not exist");
        }

        if ($state['numberOfCourseSubscriptions'] >= $state['courseCapacity']) {
            throw new RuntimeException("Course {$command->courseId->toString()} is not available");
        }

        if ($state['studentAlreadySubscribed']) {
            throw new RuntimeException("Student {$command->studentId->toString()} is already subscribed to course {$command->courseId->toString()}");
        }

        if ($state['numberOfStudentSubscriptions'] >= 5) {
            throw new RuntimeException("Student {$command->studentId->toString()} is already subscribed to 5 courses");
        }

        $this->eventAppender->append([
            new StudentSubscribedToCourse(
                $command->studentId,
                $command->courseId,
            ),
        ], $state->appendCondition);
    }
}
