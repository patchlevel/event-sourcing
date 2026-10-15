<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\CommandBus\ServiceHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\SyncCommandBus;
use Patchlevel\EventSourcing\Container\Factory;
use Patchlevel\EventSourcing\DecisionModel\DecisionFactory;
use Patchlevel\EventSourcing\DecisionModel\DecisionParameterResolver;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\AppendConditionNotMet;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Command\ChangeCourseCapacity;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Command\DefineCourse;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Command\SubscribeStudentToCourse;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\CourseId;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\CourseCapacityChanged;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\CourseDefined;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Event\StudentSubscribedToCourse;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Handler\ChangeCourseCapacityHandler;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Handler\DefineCourseHandler;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Handler\SubscribeStudentToCourseHandler;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection\CourseStudentsProjection;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\Projection\StudentCoursesProjection;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course\StudentId;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Command\CreateInvoice;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Event\InvoiceCreated;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Handler\CreateInvoiceHandler;
use Patchlevel\EventSourcing\Tests\PhpunitHelper;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DynamicConsistencyBoundaryTest extends TestCase
{
    use PhpunitHelper;

    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();
    }

    public function tearDown(): void
    {
        $this->connection->close();
    }

    public function testCourse(): void
    {
        $store = $this->createStore(__DIR__ . '/Course/Event');

        $commandBus = new SyncCommandBus(
            new ServiceHandlerProvider(
                [
                    new DefineCourseHandler(),
                    new ChangeCourseCapacityHandler(),
                    new SubscribeStudentToCourseHandler(),
                ],
                new DecisionParameterResolver(new DecisionFactory($store)),
            ),
        );

        $courseId = CourseId::generate();
        $student1Id = StudentId::generate();
        $student2Id = StudentId::generate();

        $commandBus->dispatch(new DefineCourse($courseId, 10));
        $commandBus->dispatch(new ChangeCourseCapacity($courseId, 2));
        $commandBus->dispatch(new SubscribeStudentToCourse($student1Id, $courseId));
        $commandBus->dispatch(new SubscribeStudentToCourse($student2Id, $courseId));

        self::assertStreamEquals([
            Message::create(new CourseDefined($courseId, 10))
                ->withHeader(new StreamNameHeader('main')),
            Message::create(new CourseCapacityChanged($courseId, 2))
                ->withHeader(new StreamNameHeader('main')),
            Message::create(new StudentSubscribedToCourse($student1Id, $courseId))
                ->withHeader(new StreamNameHeader('main')),
            Message::create(new StudentSubscribedToCourse($student2Id, $courseId))
                ->withHeader(new StreamNameHeader('main')),
        ], $store->load());
    }

    public function testChainedReads(): void
    {
        $store = $this->createStore(__DIR__ . '/Course/Event');
        $decisionFactory = new DecisionFactory($store);
        $commandBus = $this->courseCommandBus($decisionFactory);

        $course1Id = CourseId::generate();
        $course2Id = CourseId::generate();
        $studentId = StudentId::generate();

        $commandBus->dispatch(new DefineCourse($course1Id, 10));
        $commandBus->dispatch(new DefineCourse($course2Id, 10));
        $commandBus->dispatch(new SubscribeStudentToCourse($studentId, $course1Id));
        $commandBus->dispatch(new SubscribeStudentToCourse($studentId, $course2Id));

        $decision = $decisionFactory->begin();

        $studentIds = $decision->read(new CourseStudentsProjection($course1Id));
        self::assertEquals([$studentId], $studentIds);

        $courseIds = $decision->read(new StudentCoursesProjection($studentId));
        self::assertEquals([$course1Id, $course2Id], $courseIds);

        // the newest event only matches the second read, it must not block the append
        $decision->append([new CourseCapacityChanged($course1Id, 5)]);

        self::assertSame(5, $store->head());
    }

    public function testChainedReadsDetectConcurrentChangeOfFirstRead(): void
    {
        $store = $this->createStore(__DIR__ . '/Course/Event');
        $decisionFactory = new DecisionFactory($store);
        $commandBus = $this->courseCommandBus($decisionFactory);

        $course1Id = CourseId::generate();
        $course2Id = CourseId::generate();
        $course3Id = CourseId::generate();
        $student1Id = StudentId::generate();
        $student2Id = StudentId::generate();

        $commandBus->dispatch(new DefineCourse($course1Id, 10));
        $commandBus->dispatch(new DefineCourse($course2Id, 10));
        $commandBus->dispatch(new DefineCourse($course3Id, 10));
        $commandBus->dispatch(new SubscribeStudentToCourse($student1Id, $course1Id));
        $commandBus->dispatch(new SubscribeStudentToCourse($student1Id, $course2Id));

        $decision = $decisionFactory->begin();

        $studentIds = $decision->read(new CourseStudentsProjection($course1Id));
        self::assertEquals([$student1Id], $studentIds);

        // matches only the first read: student 2 is never looked at
        $commandBus->dispatch(new SubscribeStudentToCourse($student2Id, $course1Id));
        // matches only the second read and moves the head past the event above
        $commandBus->dispatch(new SubscribeStudentToCourse($student1Id, $course3Id));

        foreach ($studentIds as $studentId) {
            $decision->read(new StudentCoursesProjection($studentId));
        }

        $this->expectException(AppendConditionNotMet::class);

        $decision->append([new CourseCapacityChanged($course1Id, 5)]);
    }

    public function testInvoice(): void
    {
        $store = $this->createStore(__DIR__ . '/Invoice/Event');

        $commandBus = new SyncCommandBus(
            new ServiceHandlerProvider(
                [new CreateInvoiceHandler()],
                new DecisionParameterResolver(new DecisionFactory($store)),
            ),
        );

        $commandBus->dispatch(new CreateInvoice(10));
        $commandBus->dispatch(new CreateInvoice(10));
        $commandBus->dispatch(new CreateInvoice(10));
        $commandBus->dispatch(new CreateInvoice(10));
        $commandBus->dispatch(new CreateInvoice(10));

        self::assertStreamEquals([
            Message::create(new InvoiceCreated(1, 10))
                ->withHeader(new StreamNameHeader('main')),
            Message::create(new InvoiceCreated(2, 10))
                ->withHeader(new StreamNameHeader('main')),
            Message::create(new InvoiceCreated(3, 10))
                ->withHeader(new StreamNameHeader('main')),
            Message::create(new InvoiceCreated(4, 10))
                ->withHeader(new StreamNameHeader('main')),
            Message::create(new InvoiceCreated(5, 10))
                ->withHeader(new StreamNameHeader('main')),
        ], $store->load());
    }

    public function testCourseWithContainer(): void
    {
        $configuration = [
            'connection' => ['service' => 'app.connection'],
            'store' => ['type' => 'dbal_taggable', 'options' => ['default_stream_name' => 'course']],
            'events' => [__DIR__ . '/Course/Event'],
            'dcb' => true,
            'command_bus' => [
                'handlers' => [
                    DefineCourseHandler::class,
                    ChangeCourseCapacityHandler::class,
                    SubscribeStudentToCourseHandler::class,
                ],
            ],
            'services' => [
                'app.connection' => $this->connection,
                DefineCourseHandler::class => static fn (): DefineCourseHandler => new DefineCourseHandler(),
                ChangeCourseCapacityHandler::class => static fn (): ChangeCourseCapacityHandler => new ChangeCourseCapacityHandler(),
                SubscribeStudentToCourseHandler::class => static fn (): SubscribeStudentToCourseHandler => new SubscribeStudentToCourseHandler(),
            ],
        ];
        $container = Factory::create($configuration);

        $container->get(SchemaDirector::class)->create();

        $commandBus = $container->get(CommandBus::class);

        $courseId = CourseId::generate();
        $student1Id = StudentId::generate();
        $student2Id = StudentId::generate();

        $commandBus->dispatch(new DefineCourse($courseId, 10));
        $commandBus->dispatch(new ChangeCourseCapacity($courseId, 2));
        $commandBus->dispatch(new SubscribeStudentToCourse($student1Id, $courseId));
        $commandBus->dispatch(new SubscribeStudentToCourse($student2Id, $courseId));

        self::assertStreamEquals([
            Message::create(new CourseDefined($courseId, 10))
                ->withHeader(new StreamNameHeader('course')),
            Message::create(new CourseCapacityChanged($courseId, 2))
                ->withHeader(new StreamNameHeader('course')),
            Message::create(new StudentSubscribedToCourse($student1Id, $courseId))
                ->withHeader(new StreamNameHeader('course')),
            Message::create(new StudentSubscribedToCourse($student2Id, $courseId))
                ->withHeader(new StreamNameHeader('course')),
        ], $container->get(Store::class)->load());
    }

    private function createStore(string $eventPath): TaggableDoctrineDbalStore
    {
        $store = new TaggableDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([$eventPath]),
            (new AttributeEventRegistryFactory())->create([$eventPath]),
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();

        return $store;
    }

    private function courseCommandBus(DecisionFactory $decisionFactory): CommandBus
    {
        return new SyncCommandBus(
            new ServiceHandlerProvider(
                [
                    new DefineCourseHandler(),
                    new SubscribeStudentToCourseHandler(),
                ],
                new DecisionParameterResolver($decisionFactory),
            ),
        );
    }
}
