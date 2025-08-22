# Dynamic Consistency Boundary

In our little DCB getting-started example, we decide things atomically across multiple event streams without loading aggregates.
We keep the example small and show how to validate a course subscription and how to generate invoice numbers using the DCB API.

!!! note

    DCB is marked as experimental. APIs may change.

## Define some events

First we define the events that happen in our system.

A course can be defined with a capacity:

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;

#[Event('course.defined')]
final class CourseDefined
{
    public function __construct(
        #[EventTag(prefix: 'course')]
        public CourseId $courseId,
        public int $capacity,
    ) {
    }
}
```
A course capacity can change later:

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;

#[Event('course.capacity_changed')]
final class CourseCapacityChanged
{
    public function __construct(
        #[EventTag(prefix: 'course')]
        public CourseId $courseId,
        public int $capacity,
    ) {
    }
}
```
A student can subscribe to a course. We tag the event with both student and course, so that DCB projections can select the exact subset of events:

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;

#[Event('course.student_subscribed')]
final class StudentSubscribedToCourse
{
    public function __construct(
        #[EventTag(prefix: 'student')]
        public readonly StudentId $studentId,
        #[EventTag(prefix: 'course')]
        public readonly CourseId $courseId,
    ) {
    }
}
```
And finally, an invoice can be created with a monotonically increasing number:

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;

#[Event('invoice.created')]
final class InvoiceCreated
{
    public function __construct(
        #[EventTag(prefix: 'invoice')]
        public readonly int $invoiceNumber,
        public readonly int $money,
    ) {
    }
}
```

## Define projections

DCB builds a tiny, purpose-built state just for the current decision using projections.
A projection selects events via tags, processes those events and yields a small value.

We use the EventRouter trait which wires `#[Apply]` methods and builds a SubQuery from filters.

Course exists:

```php
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\DCB\BasicProjection;
use Patchlevel\EventSourcing\DCB\Projection;

/** @implements Projection<bool> */
final class CourseExists implements Projection
{
    use BasicProjection;

    public function __construct(private readonly CourseId $courseId) {}

    public function initialState(): bool
    {
        return false;
    }

    /** @return list<string> */
    public function tagFilter(): array
    {
        return ["course:{$this->courseId->toString()}"];
    }

    #[Apply]
    public function applyCourseDefined(bool $state, CourseDefined $event): bool
    {
        return true;
    }
}
```

Current capacity of a course:

```php
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\DCB\BasicProjection;
use Patchlevel\EventSourcing\DCB\Projection;

final class CourseCapacityProjection implements Projection
{
    use BasicProjection;

    public function __construct(private readonly CourseId $courseId) {}

    public function initialState(): int
    {
        return 0;
    }

    /** @return list<string> */
    public function tagFilter(): array
    {
        return ["course:{$this->courseId->toString()}"];
    }

    #[Apply]
    public function applyCourseDefined(int $state, CourseDefined $event): int
    {
        return $event->capacity;
    }

    #[Apply]
    public function applyCourseCapacityChanged(int $state, CourseCapacityChanged $event): int
    {
        return $event->capacity;
    }
}
```

Count subscriptions of a course:

```php
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\DCB\BasicProjection;
use Patchlevel\EventSourcing\DCB\Projection;

final class NumberOfCourseSubscriptionsProjection implements Projection
{
    use BasicProjection;

    public function __construct(private readonly CourseId $courseId) {}

    public function initialState(): int
    {
        return 0;
    }

    /** @return list<string> */
    public function tagFilter(): array
    {
        return ["course:{$this->courseId->toString()}"];
    }

    #[Apply]
    public function applyStudentSubscribedToCourse(int $state, StudentSubscribedToCourse $event): int
    {
        return $state + 1;
    }
}
```

Count subscriptions of a student:

```php
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\DCB\BasicProjection;
use Patchlevel\EventSourcing\DCB\Projection;

final class NumberOfStudentSubscriptionsProjection implements Projection
{
    use BasicProjection;

    public function __construct(private readonly StudentId $studentId) {}

    public function initialState(): int
    {
        return 0;
    }

    /** @return list<string> */
    public function tagFilter(): array
    {
        return ["student:{$this->studentId->toString()}"];
    }

    #[Apply]
    public function applyStudentSubscribedToCourse(int $state, StudentSubscribedToCourse $event): int
    {
        return $state + 1;
    }
}
```

Has the student already subscribed to this course:

```php
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\DCB\BasicProjection;
use Patchlevel\EventSourcing\DCB\Projection;

/** @implements Projection<bool> */
final class StudentAlreadySubscribedProjection implements Projection
{
    use BasicProjection;

    public function __construct(
        private readonly StudentId $studentId,
        private readonly CourseId $courseId,
    ) {}

    public function initialState(): bool
    {
        return false;
    }

    /** @return list<string> */
    public function tagFilter(): array
    {
        return [
            "student:{$this->studentId->toString()}",
            "course:{$this->courseId->toString()}",
        ];
    }

    #[Apply]
    public function applyStudentSubscribedToCourse(bool $state, StudentSubscribedToCourse $event): bool
    {
        return true;
    }
}
```

Next invoice number from the last event only:

```php
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\DCB\BasicProjection;
use Patchlevel\EventSourcing\DCB\Projection;

final class NextInvoiceNumberProjection implements Projection
{
    use BasicProjection;

    public function initialState(): int
    {
        return 1;
    }

    #[Apply]
    public function applyInvoiceCreated(int $state, InvoiceCreated $event): int
    {
        return $event->invoiceNumber + 1;
    }

    public function lastEventIsEnough(): bool
    {
        return true; // optimize: only the last matching event is needed
    }
}
```

!!! tip

    `#[Apply]` methods can be named freely. The second parameter’s type determines which event is routed.

## Write handlers

Now we can build decisions and append new events atomically by using the DecisionModelBuilder and EventAppender.

Define a course only if it does not exist yet:

```php
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DCB\DecisionModelBuilder;
use Patchlevel\EventSourcing\DCB\EventAppender;

final class DefineCourseHandler
{
    public function __construct(
        private readonly DecisionModelBuilder $decisionModelBuilder,
        private readonly EventAppender $eventAppender,
    ) {}

    #[Handle]
    public function __invoke(DefineCourse $command): void
    {
        $state = $this->decisionModelBuilder->build([
            'courseExists' => new CourseExists($command->courseId),
        ]);

        if ($state['courseExists']) {
            throw new RuntimeException('Course already exists');
        }

        $this->eventAppender->append([
            new CourseDefined($command->courseId, $command->capacity),
        ], $state->appendCondition);
    }
}
```

Change capacity if different:

```php
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DCB\DecisionModelBuilder;
use Patchlevel\EventSourcing\DCB\EventAppender;

final class ChangeCourseCapacityHandler
{
    public function __construct(
        private readonly DecisionModelBuilder $decisionModelBuilder,
        private readonly EventAppender $eventAppender,
    ) {}

    #[Handle]
    public function __invoke(ChangeCourseCapacity $command): void
    {
        $state = $this->decisionModelBuilder->build([
            'courseExists' => new CourseExists($command->courseId),
            'courseCapacity' => new CourseCapacityProjection($command->courseId),
        ]);

        if (!$state['courseExists']) {
            throw new RuntimeException('Course does not exist');
        }

        if ($state['courseCapacity'] === $command->capacity) {
            return;
        }

        $this->eventAppender->append([
            new CourseCapacityChanged($command->courseId, $command->capacity),
        ], $state->appendCondition);
    }
}
```

Subscribe a student with multiple checks atomically:

```php
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DCB\DecisionModelBuilder;
use Patchlevel\EventSourcing\DCB\EventAppender;

final class SubscribeStudentToCourseHandler
{
    public function __construct(
        private readonly DecisionModelBuilder $decisionModelBuilder,
        private readonly EventAppender $eventAppender,
    ) {}

    #[Handle]
    public function __invoke(SubscribeStudentToCourse $command): void
    {
        $state = $this->decisionModelBuilder->build([
            'courseExists' => new CourseExists($command->courseId),
            'courseCapacity' => new CourseCapacityProjection($command->courseId),
            'numberOfCourseSubscriptions' => new NumberOfCourseSubscriptionsProjection($command->courseId),
            'numberOfStudentSubscriptions' => new NumberOfStudentSubscriptionsProjection($command->studentId),
            'studentAlreadySubscribed' => new StudentAlreadySubscribedProjection($command->studentId, $command->courseId),
        ]);

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
            new StudentSubscribedToCourse($command->studentId, $command->courseId),
        ], $state->appendCondition);
    }
}
```

Create invoices while safely generating the next number from the last event only:

```php
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DCB\DecisionModelBuilder;
use Patchlevel\EventSourcing\DCB\EventAppender;

final class CreateInvoiceHandler
{
    public function __construct(
        private readonly DecisionModelBuilder $decisionModelBuilder,
        private readonly EventAppender $eventAppender,
    ) {}

    #[Handle]
    public function __invoke(CreateInvoice $command): void
    {
        $state = $this->decisionModelBuilder->build([
            'nextInvoiceNumber' => new NextInvoiceNumberProjection(),
        ]);

        $this->eventAppender->append([
            new InvoiceCreated($state['nextInvoiceNumber'], $command->money),
        ], $state->appendCondition);
    }
}
```

!!! tip

    If any concurrent writer changes the queried subset in between build() and append(), the store will reject the write (optimistic concurrency). You can retry if appropriate.

## Configuration

Now we plug the whole thing together. We use the TaggableDoctrineDbalStore plus the DCB builder and appender.

```php
use Patchlevel\EventSourcing\CommandBus\ServiceHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\SyncCommandBus;
use Patchlevel\EventSourcing\DCB\StoreDecisionModelBuilder;
use Patchlevel\EventSourcing\DCB\StoreEventAppender;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStore;

$store = new TaggableDoctrineDbalStore(
    $connection,
    DefaultEventSerializer::createFromPaths([__DIR__ . '/Event']),
    (new AttributeEventRegistryFactory())->create([__DIR__ . '/Event']),
);

$decisionModelBuilder = new StoreDecisionModelBuilder($store);
$eventAppender = new StoreEventAppender($store);

$commandBus = new SyncCommandBus(
    new ServiceHandlerProvider([
        new DefineCourseHandler($decisionModelBuilder, $eventAppender),
        new ChangeCourseCapacityHandler($decisionModelBuilder, $eventAppender),
        new SubscribeStudentToCourseHandler($decisionModelBuilder, $eventAppender),
        new CreateInvoiceHandler($decisionModelBuilder, $eventAppender),
    ]),
);
```

## Database setup

To actually write data to the database we need to create the event table.

```php
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;

$schemaDirector = new DoctrineSchemaDirector($connection, $store);
$schemaDirector->create();
```

!!! note

    You can also use the predefined CLI commands to create and drop the schema. See the CLI documentation.

## Usage

We are now ready to use DCB. We can dispatch commands and DCB will keep each decision consistent.

```php
$courseId = CourseId::generate();
$student1 = StudentId::generate();
$student2 = StudentId::generate();

$commandBus->dispatch(new DefineCourse($courseId, 10));
$commandBus->dispatch(new ChangeCourseCapacity($courseId, 2));
$commandBus->dispatch(new SubscribeStudentToCourse($student1, $courseId));
$commandBus->dispatch(new SubscribeStudentToCourse($student2, $courseId));

$commandBus->dispatch(new CreateInvoice(10));
$commandBus->dispatch(new CreateInvoice(10));
```

## Result

!!! success

    We have successfully implemented and used DCB to make consistent decisions across multiple streams without loading aggregates.
    Feel free to browse further in the documentation for more detailed information.

## Learn more

* [How to use command bus](command_bus.md)
* [How to use aggregates](aggregate.md)
* [How to use aggregate id](aggregate_id.md)
* [How to use clock](clock.md)

