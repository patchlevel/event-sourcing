<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Telemetry;

use ArrayObject;
use Doctrine\DBAL\Connection;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Patchlevel\EventSourcing\Message\Context\MessageContext;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\MessageDecorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\CorrelationCausationDecorator;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\Header\TraceHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\StoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepositoryManager;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Telemetry\Instrumentation;
use Patchlevel\EventSourcing\Telemetry\TraceableRepositoryManager;
use Patchlevel\EventSourcing\Telemetry\TraceAttributes;
use Patchlevel\EventSourcing\Telemetry\TraceDecorator;
use Patchlevel\EventSourcing\Telemetry\TracingSubscriber;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Telemetry\Processor\CreateInvoiceProcessor;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_values;
use function iterator_to_array;
use function str_contains;

#[CoversNothing]
final class TelemetryIntegrationTest extends TestCase
{
    private Connection $connection;

    /** @var ArrayObject<int, ImmutableSpan> */
    private ArrayObject $spanStorage;

    private TracerProvider $tracerProvider;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();

        /** @var ArrayObject<int, ImmutableSpan> $storage */
        $storage = new ArrayObject();

        $this->spanStorage = $storage;
        $this->tracerProvider = new TracerProvider(
            new SimpleSpanProcessor(new InMemoryExporter($storage)),
        );
    }

    public function tearDown(): void
    {
        $this->connection->close();
    }

    public function testTraceIsLinkedAcrossTheSubscriptionEngine(): void
    {
        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            DefaultHeadersSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $messageContext = new MessageContext();

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new TracingSubscriber($this->tracerProvider));

        $engine = new DefaultSubscriptionEngine(
            new StoreMessageLoader($store),
            new InMemorySubscriptionStore(),
            new MetadataSubscriberAccessorRepository([
                new CreateInvoiceProcessor($this->repositoryManager($store, $messageContext)),
            ]),
            eventDispatcher: $eventDispatcher,
            messageContext: $messageContext,
        );

        $manager = new RunSubscriptionEngineRepositoryManager(
            $this->repositoryManager($store, $messageContext),
            $engine,
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();
        $engine->execute(new Setup(skipBooting: true));

        // the order is placed inside an application span, which becomes the producing trace
        $instrumentation = new Instrumentation($this->tracerProvider);

        $instrumentation->span(
            'app.place_order',
            static function () use ($manager): void {
                $manager->get(Order::class)->save(
                    Order::place(OrderId::generate(), 'coffee'),
                );
            },
        );

        [$orderPlaced, $invoiceCreated] = $store->load()->toList();

        // both messages carry the trace they were recorded in
        self::assertTrue($orderPlaced->hasHeader(TraceHeader::class));
        self::assertTrue($invoiceCreated->hasHeader(TraceHeader::class));

        $applicationSpan = $this->spanByName('app.place_order');
        $processSpan = $this->spanByName(TracingSubscriber::SPAN_NAME);

        self::assertSame(SpanKind::KIND_CONSUMER, $processSpan->getKind());
        self::assertSame(
            'telemetry_create_invoice',
            $processSpan->getAttributes()->get(TraceAttributes::SUBSCRIPTION_ID),
        );

        // the processing span links back to the trace which recorded the order
        $links = $processSpan->getLinks();

        self::assertCount(1, $links);
        self::assertSame(
            $applicationSpan->getContext()->getTraceId(),
            $links[0]->getSpanContext()->getTraceId(),
        );

        // the invoice was recorded inside the processing span, so its trace context points there
        self::assertTrue(str_contains(
            $invoiceCreated->header(TraceHeader::class)->traceparent,
            $processSpan->getContext()->getTraceId(),
        ));
    }

    private function repositoryManager(Store $store, MessageContext $messageContext): RepositoryManager
    {
        $aggregateRootRegistry = new AggregateRootRegistry([
            'telemetry_order' => Order::class,
            'telemetry_invoice' => Invoice::class,
        ]);

        return new TraceableRepositoryManager(
            new DefaultRepositoryManager(
                $aggregateRootRegistry,
                $store,
                null,
                null,
                new ChainMessageDecorator([
                    new CorrelationCausationDecorator($messageContext),
                    new TraceDecorator(),
                ]),
            ),
            $aggregateRootRegistry,
            $this->tracerProvider,
        );
    }

    private function spanByName(string $name): ImmutableSpan
    {
        foreach (array_values(iterator_to_array($this->spanStorage)) as $span) {
            if ($span->getName() === $name) {
                return $span;
            }
        }

        self::fail('span not found: ' . $name);
    }
}
