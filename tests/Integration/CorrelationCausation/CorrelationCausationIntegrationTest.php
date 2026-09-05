<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\CorrelationCausation;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Message\Context\MessageContext;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\MessageDecorator\CorrelationCausationDecorator;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\Header\CausationIdHeader;
use Patchlevel\EventSourcing\Store\Header\CorrelationIdHeader;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\StoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepositoryManager;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\CorrelationCausation\Events\InvoiceCreated;
use Patchlevel\EventSourcing\Tests\Integration\CorrelationCausation\Events\OrderPlaced;
use Patchlevel\EventSourcing\Tests\Integration\CorrelationCausation\Processor\CreateInvoiceProcessor;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CorrelationCausationIntegrationTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();
    }

    public function tearDown(): void
    {
        $this->connection->close();
    }

    public function testCausationChainWithStreamStore(): void
    {
        $this->assertCausationChain(
            new StreamDoctrineDbalStore(
                $this->connection,
                DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
                DefaultHeadersSerializer::createFromPaths([__DIR__ . '/Events']),
            ),
        );
    }

    public function testCausationChainWithTaggableStore(): void
    {
        $this->assertCausationChain(
            new TaggableDoctrineDbalStore(
                $this->connection,
                DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
                (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
                DefaultHeadersSerializer::createFromPaths([__DIR__ . '/Events']),
            ),
        );
    }

    private function assertCausationChain(Store&DoctrineSchemaConfigurator $store): void
    {
        $messageContext = new MessageContext();

        $engine = new DefaultSubscriptionEngine(
            new StoreMessageLoader($store),
            new InMemorySubscriptionStore(),
            new MetadataSubscriberAccessorRepository([
                new CreateInvoiceProcessor($this->createRepositoryManager($store, $messageContext)),
            ]),
            messageContext: $messageContext,
        );

        $manager = new RunSubscriptionEngineRepositoryManager(
            $this->createRepositoryManager($store, $messageContext),
            $engine,
        );

        (new DoctrineSchemaDirector($this->connection, $store))->create();
        $engine->execute(new Setup(skipBooting: true));

        $manager->get(Order::class)->save(
            Order::place(OrderId::generate(), 'coffee'),
        );

        [$orderPlaced, $invoiceCreated] = $store->load()->toList();

        self::assertInstanceOf(OrderPlaced::class, $orderPlaced->event());
        self::assertInstanceOf(InvoiceCreated::class, $invoiceCreated->event());

        // the order is the root of the transaction: it correlates to itself and has no cause
        $orderEventId = $orderPlaced->header(EventIdHeader::class)->eventId;

        self::assertFalse($orderPlaced->hasHeader(CausationIdHeader::class));
        self::assertSame($orderEventId, $orderPlaced->header(CorrelationIdHeader::class)->correlationId);

        // the invoice was recorded while the processor handled the order event
        self::assertSame($orderEventId, $invoiceCreated->header(CausationIdHeader::class)->causationId);
        self::assertSame($orderEventId, $invoiceCreated->header(CorrelationIdHeader::class)->correlationId);

        // the context is balanced again after the engine run
        self::assertNull($messageContext->causationId());
        self::assertNull($messageContext->correlationId());
    }

    private function createRepositoryManager(Store $store, MessageContext $messageContext): RepositoryManager
    {
        return new DefaultRepositoryManager(
            new AggregateRootRegistry([
                'order' => Order::class,
                'invoice' => Invoice::class,
            ]),
            $store,
            null,
            null,
            new CorrelationCausationDecorator($messageContext),
        );
    }
}
