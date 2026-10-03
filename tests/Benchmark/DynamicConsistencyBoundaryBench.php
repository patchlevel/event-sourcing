<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark;

use Patchlevel\EventSourcing\DecisionModel\DecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\EventAppender;
use Patchlevel\EventSourcing\DecisionModel\StoreDecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\StoreEventAppender;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\AttributeEventTagExtractor;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\SubQuery;
use Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Events\CourseDefined;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Events\InvoiceCreated;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Events\StudentSubscribedToCourse;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Events\SubscriptionsCounted;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Projection\CourseCapacity;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Projection\CourseExists;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Projection\NextInvoiceNumber;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Projection\NumberOfCourseSubscriptions;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Projection\NumberOfCourseSubscriptionsWithCheckpoint;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Projection\NumberOfStudentSubscriptions;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Projection\StudentAlreadySubscribed;
use Patchlevel\EventSourcing\Tests\DbalManager;
use PhpBench\Attributes as Bench;

use function array_chunk;
use function sprintf;

/**
 * The cost of a decision depends on the size of the whole table and on how selective the tags are,
 * not only on the number of matching events. So every subject runs against the same background data:
 * 1.000 courses with 10 events each, 1.000 invoices and two courses with a long history.
 */
#[Bench\BeforeMethods('setUp')]
final class DynamicConsistencyBoundaryBench
{
    private const COURSES = 1_000;
    private const SUBSCRIPTIONS_PER_COURSE = 9;
    private const STUDENTS = 500;
    private const INVOICES = 1_000;
    private const LONG_HISTORY = 10_000;
    private const CHECKPOINT_EVERY = 100;

    private TaggableDoctrineDbalStore $store;
    private DecisionModelBuilder $decisionModelBuilder;
    private EventAppender $eventAppender;

    private int $newStudent = 0;

    public function setUp(): void
    {
        $connection = DbalManager::createConnection();

        $this->store = new TaggableDoctrineDbalStore(
            $connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/DynamicConsistencyBoundary/Events']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/DynamicConsistencyBoundary/Events']),
        );

        $this->decisionModelBuilder = new StoreDecisionModelBuilder($this->store);
        $this->eventAppender = new StoreEventAppender($this->store);

        $schemaDirector = new DoctrineSchemaDirector(
            $connection,
            $this->store,
        );

        $schemaDirector->create();

        $events = [];

        for ($course = 1; $course <= self::COURSES; $course++) {
            $events[] = new CourseDefined(sprintf('c%d', $course), 20);

            for ($i = 0; $i < self::SUBSCRIPTIONS_PER_COURSE; $i++) {
                $events[] = new StudentSubscribedToCourse(
                    sprintf('c%d', $course),
                    sprintf('s%d', ($course * self::SUBSCRIPTIONS_PER_COURSE + $i) % self::STUDENTS),
                );
            }
        }

        for ($invoice = 1; $invoice <= self::INVOICES; $invoice++) {
            $events[] = new InvoiceCreated($invoice);
        }

        $events[] = new CourseDefined('long', self::LONG_HISTORY);
        $events[] = new CourseDefined('checkpoint', self::LONG_HISTORY);

        for ($i = 1; $i <= self::LONG_HISTORY; $i++) {
            $events[] = new StudentSubscribedToCourse('long', sprintf('s%d', $i % self::STUDENTS));
            $events[] = new StudentSubscribedToCourse('checkpoint', sprintf('s%d', $i % self::STUDENTS));

            if ($i % self::CHECKPOINT_EVERY !== 0) {
                continue;
            }

            $events[] = new SubscriptionsCounted('checkpoint', $i);
        }

        $tagExtractor = new AttributeEventTagExtractor();

        foreach (array_chunk($events, 1_000) as $chunk) {
            $messages = [];

            foreach ($chunk as $event) {
                $messages[] = Message::create($event)->withHeader(new TagsHeader($tagExtractor->extract($event)));
            }

            $this->store->save(...$messages);
        }
    }

    #[Bench\Revs(10)]
    public function benchDecisionModelSmall(): void
    {
        $this->decisionModelBuilder->build([
            'courseExists' => new CourseExists('c500'),
        ]);
    }

    #[Bench\Revs(10)]
    public function benchDecisionModelComposite(): void
    {
        $this->decisionModelBuilder->build([
            'courseExists' => new CourseExists('c500'),
            'courseCapacity' => new CourseCapacity('c500'),
            'numberOfCourseSubscriptions' => new NumberOfCourseSubscriptions('c500'),
            'numberOfStudentSubscriptions' => new NumberOfStudentSubscriptions('s42'),
            'studentAlreadySubscribed' => new StudentAlreadySubscribed('c500', 's42'),
        ]);
    }

    #[Bench\Revs(10)]
    public function benchDecisionModel10000Events(): void
    {
        $this->decisionModelBuilder->build([
            'numberOfCourseSubscriptions' => new NumberOfCourseSubscriptions('long'),
        ]);
    }

    #[Bench\Revs(10)]
    public function benchDecisionModel10000EventsWithCheckpoint(): void
    {
        $this->decisionModelBuilder->build([
            'numberOfCourseSubscriptions' => new NumberOfCourseSubscriptionsWithCheckpoint('checkpoint'),
        ]);
    }

    #[Bench\Revs(10)]
    public function benchDecisionModelOnlyLastEvent(): void
    {
        $this->decisionModelBuilder->build([
            'nextInvoiceNumber' => new NextInvoiceNumber(),
        ]);
    }

    #[Bench\Revs(10)]
    public function benchDecideAndAppend(): void
    {
        $studentId = sprintf('new%d', ++$this->newStudent);

        $decisionModel = $this->decisionModelBuilder->build([
            'courseExists' => new CourseExists('c500'),
            'courseCapacity' => new CourseCapacity('c500'),
            'numberOfCourseSubscriptions' => new NumberOfCourseSubscriptions('c500'),
            'numberOfStudentSubscriptions' => new NumberOfStudentSubscriptions($studentId),
            'studentAlreadySubscribed' => new StudentAlreadySubscribed('c500', $studentId),
        ]);

        $this->eventAppender->append(
            [new StudentSubscribedToCourse('c500', $studentId)],
            $decisionModel->appendCondition,
        );
    }

    #[Bench\Revs(10)]
    public function benchAppendWithCondition(): void
    {
        $courseId = sprintf('new%d', ++$this->newStudent);

        $this->eventAppender->append(
            [new CourseDefined($courseId, 20)],
            new AppendCondition(new Query(new SubQuery(['course:' . $courseId]))),
        );
    }
}
