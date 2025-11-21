<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\CommandBus\ServiceHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\SyncCommandBus;
use Patchlevel\EventSourcing\DecisionModel\StoreDecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\StoreEventAppender;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
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
        $store = new TaggableDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Course/Event']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Course/Event']),
        );

        $decisionModelBuilder = new StoreDecisionModelBuilder($store);
        $eventAppender = new StoreEventAppender($store);

        $commandBus = new SyncCommandBus(
            new ServiceHandlerProvider([
                new DefineCourseHandler($decisionModelBuilder, $eventAppender),
                new ChangeCourseCapacityHandler($decisionModelBuilder, $eventAppender),
                new SubscribeStudentToCourseHandler($decisionModelBuilder, $eventAppender),
            ]),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

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

    public function testInvoice(): void
    {
        $store = new TaggableDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Invoice/Event']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Invoice/Event']),
        );

        $decisionModelBuilder = new StoreDecisionModelBuilder($store);
        $eventAppender = new StoreEventAppender($store);

        $commandBus = new SyncCommandBus(
            new ServiceHandlerProvider([
                new CreateInvoiceHandler($decisionModelBuilder, $eventAppender),
            ]),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

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
}
