<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Handler;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DecisionModel\DecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\EventAppender;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Command\DefineCourse;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\CourseDefined;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection\CourseExists;
use RuntimeException;

final class DefineCourseHandler
{
    public function __construct(
        private readonly DecisionModelBuilder $decisionModelBuilder,
        private readonly EventAppender $eventAppender,
    ) {
    }

    #[Handle]
    public function __invoke(DefineCourse $command): void
    {
        $state = $this->decisionModelBuilder->build(
            [
                'courseExists' => new CourseExists($command->courseId),
            ],
        );

        if ($state['courseExists']) {
            throw new RuntimeException('Course already exists');
        }

        $this->eventAppender->append([
            new CourseDefined(
                $command->courseId,
                $command->capacity,
            ),
        ], $state->appendCondition);
    }
}
