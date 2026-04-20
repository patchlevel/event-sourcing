<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Container;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Patchlevel\EventSourcing\Attribute\Answer;
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\CommandBus\InstantRetryCommandBus;
use Patchlevel\EventSourcing\Console\Command\DebugCommand;
use Patchlevel\EventSourcing\Console\Command\ShowAggregateCommand;
use Patchlevel\EventSourcing\Console\Command\ShowCommand;
use Patchlevel\EventSourcing\Console\Command\StoreMigrateCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionBootCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionPauseCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionReactivateCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionRemoveCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionRunCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionSetupCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionStatusCommand;
use Patchlevel\EventSourcing\Console\Command\SubscriptionTeardownCommand;
use Patchlevel\EventSourcing\Console\Command\WatchCommand;
use Patchlevel\EventSourcing\Container\Configuration;
use Patchlevel\EventSourcing\Container\Container;
use Patchlevel\EventSourcing\Container\Factory;
use Patchlevel\EventSourcing\Container\InvalidConfiguration;
use Patchlevel\EventSourcing\Cryptography\DoctrineCipherKeyStore;
use Patchlevel\EventSourcing\DecisionModel\DecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\EventAppender;
use Patchlevel\EventSourcing\DecisionModel\StoreDecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\StoreEventAppender;
use Patchlevel\EventSourcing\EventBus\AttributeListenerProvider;
use Patchlevel\EventSourcing\EventBus\Consumer;
use Patchlevel\EventSourcing\EventBus\DefaultConsumer;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\ListenerProvider;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Message\Translator\ExcludeEventTranslator;
use Patchlevel\EventSourcing\Message\Translator\ExtractEventTagTranslator;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataAwareMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistry;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Projection\ProjectionBuilder;
use Patchlevel\EventSourcing\Projection\StoreProjectionBuilder;
use Patchlevel\EventSourcing\QueryBus\QueryBus;
use Patchlevel\EventSourcing\QueryBus\SyncQueryBus;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\MessageDecorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaListener;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Patchlevel\EventSourcing\Serializer\AttributeEventTagExtractor;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\EventTagExtractor;
use Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr16SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\Adapter\Psr6SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\AdapterRepository;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\Dbal\PostgreSQLPlatform;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\ReadOnlyStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Cleanup\Cleaner;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropTableTask;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionRemoved;
use Patchlevel\EventSourcing\Subscription\Engine\EventFilteredStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\GapResolverStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\StoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\ThrowOnErrorSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepositoryManager;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\NoRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Container\Fixture\ArrayContainer;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\OtherQueryProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\QueryAnsweringProjection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\QueryProfile;
use Patchlevel\Hydrator\Extension\Cryptography\BaseCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKeyFactory;
use Patchlevel\Hydrator\Extension\Cryptography\Cryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Extension\Upcast\CallbackUpcaster;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\StackHydrator;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function array_keys;
use function array_map;
use function is_string;
use function strtolower;

final class FactoryTest extends TestCase
{
    public function testCreateConnectionUrlContainer(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(DefaultRepositoryManager::class, $container->get(RepositoryManager::class));
        self::assertInstanceOf(AttributeEventMetadataFactory::class, $container->get(EventMetadataFactory::class));
        self::assertInstanceOf(JsonEncoder::class, $container->get(Encoder::class));
        self::assertInstanceOf(AttributeMessageHeaderRegistryFactory::class, $container->get(MessageHeaderRegistryFactory::class));
        self::assertInstanceOf(AggregateRootMetadataAwareMetadataFactory::class, $container->get(AggregateRootMetadataFactory::class));
        self::assertInstanceOf(AttributeSubscriberMetadataFactory::class, $container->get(SubscriberMetadataFactory::class));
        self::assertInstanceOf(Connection::class, $container->get(Factory::CONNECTION_ID));
        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(EventRegistry::class, $container->get(EventRegistry::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(MessageHeaderRegistry::class, $container->get(MessageHeaderRegistry::class));
        self::assertInstanceOf(DefaultHeadersSerializer::class, $container->get(HeadersSerializer::class));
        self::assertInstanceOf(StackHydrator::class, $container->get(Hydrator::class));
        self::assertInstanceOf(SystemClock::class, $container->get(ClockInterface::class));
        self::assertInstanceOf(AggregateRootRegistry::class, $container->get(AggregateRootRegistry::class));
        self::assertInstanceOf(ShowCommand::class, $container->get(ShowCommand::class));
        self::assertInstanceOf(ShowAggregateCommand::class, $container->get(ShowAggregateCommand::class));
        self::assertInstanceOf(WatchCommand::class, $container->get(WatchCommand::class));
        self::assertInstanceOf(DebugCommand::class, $container->get(DebugCommand::class));
        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
        self::assertInstanceOf(InstantRetryCommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(SyncQueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(StoreMessageLoader::class, $container->get(MessageLoader::class));
        self::assertFalse($container->has(ListenerProvider::class));
        self::assertFalse($container->has(Consumer::class));
        self::assertFalse($container->has(EventBus::class));
        self::assertFalse($container->has(SnapshotStore::class));
        self::assertInstanceOf(RetryStrategyRepository::class, $container->get(RetryStrategyRepository::class));
        self::assertInstanceOf(DoctrineSubscriptionStore::class, $container->get(SubscriptionStore::class));
        self::assertInstanceOf(MetadataSubscriberAccessorRepository::class, $container->get(SubscriberAccessorRepository::class));
        self::assertInstanceOf(DefaultSubscriptionEngine::class, $container->get(SubscriptionEngine::class));
        self::assertInstanceOf(SubscriptionSetupCommand::class, $container->get(SubscriptionSetupCommand::class));
        self::assertInstanceOf(SubscriptionBootCommand::class, $container->get(SubscriptionBootCommand::class));
        self::assertInstanceOf(SubscriptionRunCommand::class, $container->get(SubscriptionRunCommand::class));
        self::assertInstanceOf(SubscriptionTeardownCommand::class, $container->get(SubscriptionTeardownCommand::class));
        self::assertInstanceOf(SubscriptionRemoveCommand::class, $container->get(SubscriptionRemoveCommand::class));
        self::assertInstanceOf(SubscriptionStatusCommand::class, $container->get(SubscriptionStatusCommand::class));
        self::assertInstanceOf(SubscriptionPauseCommand::class, $container->get(SubscriptionPauseCommand::class));
        self::assertInstanceOf(SubscriptionReactivateCommand::class, $container->get(SubscriptionReactivateCommand::class));
        self::assertFalse($container->has(CipherKeyFactory::class));
        self::assertFalse($container->has(CipherKeyStore::class));
        self::assertFalse($container->has(Cryptographer::class));
    }

    public function testCreateConnectionServiceContainer(): void
    {
        $connection = DriverManager::getConnection((new DsnParser())->parse('sqlite3:///:memory:'));
        $configuration = [
            'connection' => ['service' => 'app.connection'],
            'services' => ['app.connection' => $connection],
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(DefaultRepositoryManager::class, $container->get(RepositoryManager::class));
        self::assertInstanceOf(AttributeEventMetadataFactory::class, $container->get(EventMetadataFactory::class));
        self::assertInstanceOf(JsonEncoder::class, $container->get(Encoder::class));
        self::assertInstanceOf(AttributeMessageHeaderRegistryFactory::class, $container->get(MessageHeaderRegistryFactory::class));
        self::assertInstanceOf(AggregateRootMetadataAwareMetadataFactory::class, $container->get(AggregateRootMetadataFactory::class));
        self::assertInstanceOf(AttributeSubscriberMetadataFactory::class, $container->get(SubscriberMetadataFactory::class));
        self::assertInstanceOf(Connection::class, $container->get(Factory::CONNECTION_ID));
        self::assertSame($connection, $container->get(Factory::CONNECTION_ID));
        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(EventRegistry::class, $container->get(EventRegistry::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(MessageHeaderRegistry::class, $container->get(MessageHeaderRegistry::class));
        self::assertInstanceOf(DefaultHeadersSerializer::class, $container->get(HeadersSerializer::class));
        self::assertInstanceOf(StackHydrator::class, $container->get(Hydrator::class));
        self::assertInstanceOf(SystemClock::class, $container->get(ClockInterface::class));
        self::assertInstanceOf(AggregateRootRegistry::class, $container->get(AggregateRootRegistry::class));
        self::assertInstanceOf(ShowCommand::class, $container->get(ShowCommand::class));
        self::assertInstanceOf(ShowAggregateCommand::class, $container->get(ShowAggregateCommand::class));
        self::assertInstanceOf(WatchCommand::class, $container->get(WatchCommand::class));
        self::assertInstanceOf(DebugCommand::class, $container->get(DebugCommand::class));
        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
        self::assertInstanceOf(InstantRetryCommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(SyncQueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(StoreMessageLoader::class, $container->get(MessageLoader::class));
        self::assertFalse($container->has(ListenerProvider::class));
        self::assertFalse($container->has(Consumer::class));
        self::assertFalse($container->has(EventBus::class));
        self::assertFalse($container->has(SnapshotStore::class));
        self::assertInstanceOf(RetryStrategyRepository::class, $container->get(RetryStrategyRepository::class));
        self::assertInstanceOf(DoctrineSubscriptionStore::class, $container->get(SubscriptionStore::class));
        self::assertInstanceOf(MetadataSubscriberAccessorRepository::class, $container->get(SubscriberAccessorRepository::class));
        self::assertInstanceOf(DefaultSubscriptionEngine::class, $container->get(SubscriptionEngine::class));
        self::assertInstanceOf(SubscriptionSetupCommand::class, $container->get(SubscriptionSetupCommand::class));
        self::assertInstanceOf(SubscriptionBootCommand::class, $container->get(SubscriptionBootCommand::class));
        self::assertInstanceOf(SubscriptionRunCommand::class, $container->get(SubscriptionRunCommand::class));
        self::assertInstanceOf(SubscriptionTeardownCommand::class, $container->get(SubscriptionTeardownCommand::class));
        self::assertInstanceOf(SubscriptionRemoveCommand::class, $container->get(SubscriptionRemoveCommand::class));
        self::assertInstanceOf(SubscriptionStatusCommand::class, $container->get(SubscriptionStatusCommand::class));
        self::assertInstanceOf(SubscriptionPauseCommand::class, $container->get(SubscriptionPauseCommand::class));
        self::assertInstanceOf(SubscriptionReactivateCommand::class, $container->get(SubscriptionReactivateCommand::class));
        self::assertFalse($container->has(CipherKeyFactory::class));
        self::assertFalse($container->has(CipherKeyStore::class));
        self::assertFalse($container->has(Cryptographer::class));
    }

    public function testCreateWithFeatures(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'hydrator' => ['default_lazy' => true, 'cryptography' => true, 'lifecycle' => true],
            'subscription' => ['gap_detection' => true],
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(DefaultRepositoryManager::class, $container->get(RepositoryManager::class));
        self::assertInstanceOf(AttributeEventMetadataFactory::class, $container->get(EventMetadataFactory::class));
        self::assertInstanceOf(JsonEncoder::class, $container->get(Encoder::class));
        self::assertInstanceOf(AttributeMessageHeaderRegistryFactory::class, $container->get(MessageHeaderRegistryFactory::class));
        self::assertInstanceOf(AggregateRootMetadataAwareMetadataFactory::class, $container->get(AggregateRootMetadataFactory::class));
        self::assertInstanceOf(AttributeSubscriberMetadataFactory::class, $container->get(SubscriberMetadataFactory::class));
        self::assertInstanceOf(Connection::class, $container->get(Factory::CONNECTION_ID));
        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(EventRegistry::class, $container->get(EventRegistry::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(MessageHeaderRegistry::class, $container->get(MessageHeaderRegistry::class));
        self::assertInstanceOf(DefaultHeadersSerializer::class, $container->get(HeadersSerializer::class));
        self::assertInstanceOf(StackHydrator::class, $container->get(Hydrator::class));
        self::assertInstanceOf(SystemClock::class, $container->get(ClockInterface::class));
        self::assertInstanceOf(AggregateRootRegistry::class, $container->get(AggregateRootRegistry::class));
        self::assertInstanceOf(ShowCommand::class, $container->get(ShowCommand::class));
        self::assertInstanceOf(ShowAggregateCommand::class, $container->get(ShowAggregateCommand::class));
        self::assertInstanceOf(WatchCommand::class, $container->get(WatchCommand::class));
        self::assertInstanceOf(DebugCommand::class, $container->get(DebugCommand::class));
        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
        self::assertInstanceOf(InstantRetryCommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(SyncQueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(GapResolverStoreMessageLoader::class, $container->get(MessageLoader::class));
        self::assertFalse($container->has(ListenerProvider::class));
        self::assertFalse($container->has(Consumer::class));
        self::assertFalse($container->has(EventBus::class));
        self::assertFalse($container->has(SnapshotStore::class));
        self::assertInstanceOf(RetryStrategyRepository::class, $container->get(RetryStrategyRepository::class));
        self::assertInstanceOf(DoctrineSubscriptionStore::class, $container->get(SubscriptionStore::class));
        self::assertInstanceOf(MetadataSubscriberAccessorRepository::class, $container->get(SubscriberAccessorRepository::class));
        self::assertInstanceOf(DefaultSubscriptionEngine::class, $container->get(SubscriptionEngine::class));
        self::assertInstanceOf(SubscriptionSetupCommand::class, $container->get(SubscriptionSetupCommand::class));
        self::assertInstanceOf(SubscriptionBootCommand::class, $container->get(SubscriptionBootCommand::class));
        self::assertInstanceOf(SubscriptionRunCommand::class, $container->get(SubscriptionRunCommand::class));
        self::assertInstanceOf(SubscriptionTeardownCommand::class, $container->get(SubscriptionTeardownCommand::class));
        self::assertInstanceOf(SubscriptionRemoveCommand::class, $container->get(SubscriptionRemoveCommand::class));
        self::assertInstanceOf(SubscriptionStatusCommand::class, $container->get(SubscriptionStatusCommand::class));
        self::assertInstanceOf(SubscriptionPauseCommand::class, $container->get(SubscriptionPauseCommand::class));
        self::assertInstanceOf(SubscriptionReactivateCommand::class, $container->get(SubscriptionReactivateCommand::class));
        self::assertInstanceOf(DoctrineCipherKeyStore::class, $container->get(CipherKeyStore::class));
        self::assertInstanceOf(BaseCryptographer::class, $container->get(Cryptographer::class));
    }

    public function testCreateWithSyncSubscriptionsAndThrowOnError(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'hydrator' => ['default_lazy' => true, 'cryptography' => true, 'lifecycle' => true],
            'subscription' => ['gap_detection' => true, 'sync' => ['throw_on_error' => true]],
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(AttributeEventMetadataFactory::class, $container->get(EventMetadataFactory::class));
        self::assertInstanceOf(JsonEncoder::class, $container->get(Encoder::class));
        self::assertInstanceOf(AttributeMessageHeaderRegistryFactory::class, $container->get(MessageHeaderRegistryFactory::class));
        self::assertInstanceOf(AggregateRootMetadataAwareMetadataFactory::class, $container->get(AggregateRootMetadataFactory::class));
        self::assertInstanceOf(AttributeSubscriberMetadataFactory::class, $container->get(SubscriberMetadataFactory::class));
        self::assertInstanceOf(Connection::class, $container->get(Factory::CONNECTION_ID));
        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(EventRegistry::class, $container->get(EventRegistry::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(MessageHeaderRegistry::class, $container->get(MessageHeaderRegistry::class));
        self::assertInstanceOf(DefaultHeadersSerializer::class, $container->get(HeadersSerializer::class));
        self::assertInstanceOf(StackHydrator::class, $container->get(Hydrator::class));
        self::assertInstanceOf(SystemClock::class, $container->get(ClockInterface::class));
        self::assertInstanceOf(AggregateRootRegistry::class, $container->get(AggregateRootRegistry::class));
        self::assertInstanceOf(ShowCommand::class, $container->get(ShowCommand::class));
        self::assertInstanceOf(ShowAggregateCommand::class, $container->get(ShowAggregateCommand::class));
        self::assertInstanceOf(WatchCommand::class, $container->get(WatchCommand::class));
        self::assertInstanceOf(DebugCommand::class, $container->get(DebugCommand::class));
        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
        self::assertInstanceOf(InstantRetryCommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(SyncQueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(GapResolverStoreMessageLoader::class, $container->get(MessageLoader::class));
        self::assertFalse($container->has(ListenerProvider::class));
        self::assertFalse($container->has(Consumer::class));
        self::assertFalse($container->has(EventBus::class));
        self::assertFalse($container->has(SnapshotStore::class));
        self::assertInstanceOf(RetryStrategyRepository::class, $container->get(RetryStrategyRepository::class));
        self::assertInstanceOf(DoctrineSubscriptionStore::class, $container->get(SubscriptionStore::class));
        self::assertInstanceOf(MetadataSubscriberAccessorRepository::class, $container->get(SubscriberAccessorRepository::class));
        self::assertInstanceOf(DefaultSubscriptionEngine::class, $container->get(SubscriptionEngine::class));
        self::assertInstanceOf(ThrowOnErrorSubscriptionEngine::class, $container->get(Factory::SUBSCRIPTION_SYNC_ENGINE_ID));
        self::assertInstanceOf(RunSubscriptionEngineRepositoryManager::class, $container->get(RepositoryManager::class));
        self::assertInstanceOf(SubscriptionSetupCommand::class, $container->get(SubscriptionSetupCommand::class));
        self::assertInstanceOf(SubscriptionBootCommand::class, $container->get(SubscriptionBootCommand::class));
        self::assertInstanceOf(SubscriptionRunCommand::class, $container->get(SubscriptionRunCommand::class));
        self::assertInstanceOf(SubscriptionTeardownCommand::class, $container->get(SubscriptionTeardownCommand::class));
        self::assertInstanceOf(SubscriptionRemoveCommand::class, $container->get(SubscriptionRemoveCommand::class));
        self::assertInstanceOf(SubscriptionStatusCommand::class, $container->get(SubscriptionStatusCommand::class));
        self::assertInstanceOf(SubscriptionPauseCommand::class, $container->get(SubscriptionPauseCommand::class));
        self::assertInstanceOf(SubscriptionReactivateCommand::class, $container->get(SubscriptionReactivateCommand::class));
        self::assertInstanceOf(DoctrineCipherKeyStore::class, $container->get(CipherKeyStore::class));
        self::assertInstanceOf(BaseCryptographer::class, $container->get(Cryptographer::class));
    }

    public function testCreateWithSyncSubscriptionsAndCatchUpLimit(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'hydrator' => ['default_lazy' => true, 'cryptography' => true, 'lifecycle' => true],
            'subscription' => ['gap_detection' => true, 'sync' => ['throw_on_error' => true, 'catch_up_limit' => 10]],
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(AttributeEventMetadataFactory::class, $container->get(EventMetadataFactory::class));
        self::assertInstanceOf(JsonEncoder::class, $container->get(Encoder::class));
        self::assertInstanceOf(AttributeMessageHeaderRegistryFactory::class, $container->get(MessageHeaderRegistryFactory::class));
        self::assertInstanceOf(AggregateRootMetadataAwareMetadataFactory::class, $container->get(AggregateRootMetadataFactory::class));
        self::assertInstanceOf(AttributeSubscriberMetadataFactory::class, $container->get(SubscriberMetadataFactory::class));
        self::assertInstanceOf(Connection::class, $container->get(Factory::CONNECTION_ID));
        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(EventRegistry::class, $container->get(EventRegistry::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(MessageHeaderRegistry::class, $container->get(MessageHeaderRegistry::class));
        self::assertInstanceOf(DefaultHeadersSerializer::class, $container->get(HeadersSerializer::class));
        self::assertInstanceOf(StackHydrator::class, $container->get(Hydrator::class));
        self::assertInstanceOf(SystemClock::class, $container->get(ClockInterface::class));
        self::assertInstanceOf(AggregateRootRegistry::class, $container->get(AggregateRootRegistry::class));
        self::assertInstanceOf(ShowCommand::class, $container->get(ShowCommand::class));
        self::assertInstanceOf(ShowAggregateCommand::class, $container->get(ShowAggregateCommand::class));
        self::assertInstanceOf(WatchCommand::class, $container->get(WatchCommand::class));
        self::assertInstanceOf(DebugCommand::class, $container->get(DebugCommand::class));
        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
        self::assertInstanceOf(InstantRetryCommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(SyncQueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(GapResolverStoreMessageLoader::class, $container->get(MessageLoader::class));
        self::assertFalse($container->has(ListenerProvider::class));
        self::assertFalse($container->has(Consumer::class));
        self::assertFalse($container->has(EventBus::class));
        self::assertFalse($container->has(SnapshotStore::class));
        self::assertInstanceOf(RetryStrategyRepository::class, $container->get(RetryStrategyRepository::class));
        self::assertInstanceOf(DoctrineSubscriptionStore::class, $container->get(SubscriptionStore::class));
        self::assertInstanceOf(MetadataSubscriberAccessorRepository::class, $container->get(SubscriberAccessorRepository::class));
        self::assertInstanceOf(DefaultSubscriptionEngine::class, $container->get(SubscriptionEngine::class));
        self::assertInstanceOf(ThrowOnErrorSubscriptionEngine::class, $container->get(Factory::SUBSCRIPTION_SYNC_ENGINE_ID));
        self::assertInstanceOf(RunSubscriptionEngineRepositoryManager::class, $container->get(RepositoryManager::class));
        self::assertInstanceOf(SubscriptionSetupCommand::class, $container->get(SubscriptionSetupCommand::class));
        self::assertInstanceOf(SubscriptionBootCommand::class, $container->get(SubscriptionBootCommand::class));
        self::assertInstanceOf(SubscriptionRunCommand::class, $container->get(SubscriptionRunCommand::class));
        self::assertInstanceOf(SubscriptionTeardownCommand::class, $container->get(SubscriptionTeardownCommand::class));
        self::assertInstanceOf(SubscriptionRemoveCommand::class, $container->get(SubscriptionRemoveCommand::class));
        self::assertInstanceOf(SubscriptionStatusCommand::class, $container->get(SubscriptionStatusCommand::class));
        self::assertInstanceOf(SubscriptionPauseCommand::class, $container->get(SubscriptionPauseCommand::class));
        self::assertInstanceOf(SubscriptionReactivateCommand::class, $container->get(SubscriptionReactivateCommand::class));
        self::assertInstanceOf(DoctrineCipherKeyStore::class, $container->get(CipherKeyStore::class));
        self::assertInstanceOf(BaseCryptographer::class, $container->get(Cryptographer::class));
    }

    public function testCreateWithEventBus(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'hydrator' => ['default_lazy' => true, 'cryptography' => true, 'lifecycle' => true],
            'subscription' => ['gap_detection' => true],
            'event_bus' => true,
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(DefaultRepositoryManager::class, $container->get(RepositoryManager::class));
        self::assertInstanceOf(AttributeEventMetadataFactory::class, $container->get(EventMetadataFactory::class));
        self::assertInstanceOf(JsonEncoder::class, $container->get(Encoder::class));
        self::assertInstanceOf(AttributeMessageHeaderRegistryFactory::class, $container->get(MessageHeaderRegistryFactory::class));
        self::assertInstanceOf(AggregateRootMetadataAwareMetadataFactory::class, $container->get(AggregateRootMetadataFactory::class));
        self::assertInstanceOf(AttributeSubscriberMetadataFactory::class, $container->get(SubscriberMetadataFactory::class));
        self::assertInstanceOf(Connection::class, $container->get(Factory::CONNECTION_ID));
        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(EventRegistry::class, $container->get(EventRegistry::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(MessageHeaderRegistry::class, $container->get(MessageHeaderRegistry::class));
        self::assertInstanceOf(DefaultHeadersSerializer::class, $container->get(HeadersSerializer::class));
        self::assertInstanceOf(StackHydrator::class, $container->get(Hydrator::class));
        self::assertInstanceOf(SystemClock::class, $container->get(ClockInterface::class));
        self::assertInstanceOf(AggregateRootRegistry::class, $container->get(AggregateRootRegistry::class));
        self::assertInstanceOf(ShowCommand::class, $container->get(ShowCommand::class));
        self::assertInstanceOf(ShowAggregateCommand::class, $container->get(ShowAggregateCommand::class));
        self::assertInstanceOf(WatchCommand::class, $container->get(WatchCommand::class));
        self::assertInstanceOf(DebugCommand::class, $container->get(DebugCommand::class));
        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
        self::assertInstanceOf(InstantRetryCommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(SyncQueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(GapResolverStoreMessageLoader::class, $container->get(MessageLoader::class));
        self::assertInstanceOf(AttributeListenerProvider::class, $container->get(ListenerProvider::class));
        self::assertInstanceOf(DefaultConsumer::class, $container->get(Consumer::class));
        self::assertInstanceOf(DefaultEventBus::class, $container->get(EventBus::class));
        self::assertFalse($container->has(SnapshotStore::class));
        self::assertInstanceOf(RetryStrategyRepository::class, $container->get(RetryStrategyRepository::class));
        self::assertInstanceOf(DoctrineSubscriptionStore::class, $container->get(SubscriptionStore::class));
        self::assertInstanceOf(MetadataSubscriberAccessorRepository::class, $container->get(SubscriberAccessorRepository::class));
        self::assertInstanceOf(DefaultSubscriptionEngine::class, $container->get(SubscriptionEngine::class));
        self::assertInstanceOf(SubscriptionSetupCommand::class, $container->get(SubscriptionSetupCommand::class));
        self::assertInstanceOf(SubscriptionBootCommand::class, $container->get(SubscriptionBootCommand::class));
        self::assertInstanceOf(SubscriptionRunCommand::class, $container->get(SubscriptionRunCommand::class));
        self::assertInstanceOf(SubscriptionTeardownCommand::class, $container->get(SubscriptionTeardownCommand::class));
        self::assertInstanceOf(SubscriptionRemoveCommand::class, $container->get(SubscriptionRemoveCommand::class));
        self::assertInstanceOf(SubscriptionStatusCommand::class, $container->get(SubscriptionStatusCommand::class));
        self::assertInstanceOf(SubscriptionPauseCommand::class, $container->get(SubscriptionPauseCommand::class));
        self::assertInstanceOf(SubscriptionReactivateCommand::class, $container->get(SubscriptionReactivateCommand::class));
        self::assertInstanceOf(DoctrineCipherKeyStore::class, $container->get(CipherKeyStore::class));
        self::assertInstanceOf(BaseCryptographer::class, $container->get(Cryptographer::class));
    }

    public function testCreateWithSnapshots(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'hydrator' => ['default_lazy' => true, 'cryptography' => true, 'lifecycle' => true],
            'subscription' => ['gap_detection' => true],
            'snapshot_stores' => ['default' => new InMemorySnapshotAdapter()],
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(DefaultRepositoryManager::class, $container->get(RepositoryManager::class));
        self::assertInstanceOf(AttributeEventMetadataFactory::class, $container->get(EventMetadataFactory::class));
        self::assertInstanceOf(JsonEncoder::class, $container->get(Encoder::class));
        self::assertInstanceOf(AttributeMessageHeaderRegistryFactory::class, $container->get(MessageHeaderRegistryFactory::class));
        self::assertInstanceOf(AggregateRootMetadataAwareMetadataFactory::class, $container->get(AggregateRootMetadataFactory::class));
        self::assertInstanceOf(AttributeSubscriberMetadataFactory::class, $container->get(SubscriberMetadataFactory::class));
        self::assertInstanceOf(Connection::class, $container->get(Factory::CONNECTION_ID));
        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(EventRegistry::class, $container->get(EventRegistry::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(MessageHeaderRegistry::class, $container->get(MessageHeaderRegistry::class));
        self::assertInstanceOf(DefaultHeadersSerializer::class, $container->get(HeadersSerializer::class));
        self::assertInstanceOf(StackHydrator::class, $container->get(Hydrator::class));
        self::assertInstanceOf(SystemClock::class, $container->get(ClockInterface::class));
        self::assertInstanceOf(AggregateRootRegistry::class, $container->get(AggregateRootRegistry::class));
        self::assertInstanceOf(ShowCommand::class, $container->get(ShowCommand::class));
        self::assertInstanceOf(ShowAggregateCommand::class, $container->get(ShowAggregateCommand::class));
        self::assertInstanceOf(WatchCommand::class, $container->get(WatchCommand::class));
        self::assertInstanceOf(DebugCommand::class, $container->get(DebugCommand::class));
        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
        self::assertInstanceOf(InstantRetryCommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(SyncQueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(GapResolverStoreMessageLoader::class, $container->get(MessageLoader::class));
        self::assertFalse($container->has(ListenerProvider::class));
        self::assertFalse($container->has(Consumer::class));
        self::assertFalse($container->has(EventBus::class));
        self::assertInstanceOf(DefaultSnapshotStore::class, $container->get(SnapshotStore::class));
        self::assertInstanceOf(RetryStrategyRepository::class, $container->get(RetryStrategyRepository::class));
        self::assertInstanceOf(DoctrineSubscriptionStore::class, $container->get(SubscriptionStore::class));
        self::assertInstanceOf(MetadataSubscriberAccessorRepository::class, $container->get(SubscriberAccessorRepository::class));
        self::assertInstanceOf(DefaultSubscriptionEngine::class, $container->get(SubscriptionEngine::class));
        self::assertInstanceOf(SubscriptionSetupCommand::class, $container->get(SubscriptionSetupCommand::class));
        self::assertInstanceOf(SubscriptionBootCommand::class, $container->get(SubscriptionBootCommand::class));
        self::assertInstanceOf(SubscriptionRunCommand::class, $container->get(SubscriptionRunCommand::class));
        self::assertInstanceOf(SubscriptionTeardownCommand::class, $container->get(SubscriptionTeardownCommand::class));
        self::assertInstanceOf(SubscriptionRemoveCommand::class, $container->get(SubscriptionRemoveCommand::class));
        self::assertInstanceOf(SubscriptionStatusCommand::class, $container->get(SubscriptionStatusCommand::class));
        self::assertInstanceOf(SubscriptionPauseCommand::class, $container->get(SubscriptionPauseCommand::class));
        self::assertInstanceOf(SubscriptionReactivateCommand::class, $container->get(SubscriptionReactivateCommand::class));
        self::assertInstanceOf(DoctrineCipherKeyStore::class, $container->get(CipherKeyStore::class));
        self::assertInstanceOf(BaseCryptographer::class, $container->get(Cryptographer::class));
    }

    public function testCreateWithRetryStrategy(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'hydrator' => ['default_lazy' => true, 'cryptography' => true, 'lifecycle' => true],
            'subscription' => ['gap_detection' => true, 'retry_strategies' => ['default' => ['type' => 'clock_based', 'options' => ['base_delay' => 10]]]],
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(DefaultRepositoryManager::class, $container->get(RepositoryManager::class));
        self::assertInstanceOf(AttributeEventMetadataFactory::class, $container->get(EventMetadataFactory::class));
        self::assertInstanceOf(JsonEncoder::class, $container->get(Encoder::class));
        self::assertInstanceOf(AttributeMessageHeaderRegistryFactory::class, $container->get(MessageHeaderRegistryFactory::class));
        self::assertInstanceOf(AggregateRootMetadataAwareMetadataFactory::class, $container->get(AggregateRootMetadataFactory::class));
        self::assertInstanceOf(AttributeSubscriberMetadataFactory::class, $container->get(SubscriberMetadataFactory::class));
        self::assertInstanceOf(Connection::class, $container->get(Factory::CONNECTION_ID));
        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(EventRegistry::class, $container->get(EventRegistry::class));
        self::assertInstanceOf(DefaultEventSerializer::class, $container->get(EventSerializer::class));
        self::assertInstanceOf(MessageHeaderRegistry::class, $container->get(MessageHeaderRegistry::class));
        self::assertInstanceOf(DefaultHeadersSerializer::class, $container->get(HeadersSerializer::class));
        self::assertInstanceOf(StackHydrator::class, $container->get(Hydrator::class));
        self::assertInstanceOf(SystemClock::class, $container->get(ClockInterface::class));
        self::assertInstanceOf(AggregateRootRegistry::class, $container->get(AggregateRootRegistry::class));
        self::assertInstanceOf(ShowCommand::class, $container->get(ShowCommand::class));
        self::assertInstanceOf(ShowAggregateCommand::class, $container->get(ShowAggregateCommand::class));
        self::assertInstanceOf(WatchCommand::class, $container->get(WatchCommand::class));
        self::assertInstanceOf(DebugCommand::class, $container->get(DebugCommand::class));
        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
        self::assertInstanceOf(InstantRetryCommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(SyncQueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(GapResolverStoreMessageLoader::class, $container->get(MessageLoader::class));
        self::assertFalse($container->has(ListenerProvider::class));
        self::assertFalse($container->has(Consumer::class));
        self::assertFalse($container->has(EventBus::class));
        self::assertFalse($container->has(SnapshotStore::class));
        self::assertInstanceOf(RetryStrategyRepository::class, $container->get(RetryStrategyRepository::class));
        self::assertInstanceOf(DoctrineSubscriptionStore::class, $container->get(SubscriptionStore::class));
        self::assertInstanceOf(MetadataSubscriberAccessorRepository::class, $container->get(SubscriberAccessorRepository::class));
        self::assertInstanceOf(DefaultSubscriptionEngine::class, $container->get(SubscriptionEngine::class));
        self::assertInstanceOf(SubscriptionSetupCommand::class, $container->get(SubscriptionSetupCommand::class));
        self::assertInstanceOf(SubscriptionBootCommand::class, $container->get(SubscriptionBootCommand::class));
        self::assertInstanceOf(SubscriptionRunCommand::class, $container->get(SubscriptionRunCommand::class));
        self::assertInstanceOf(SubscriptionTeardownCommand::class, $container->get(SubscriptionTeardownCommand::class));
        self::assertInstanceOf(SubscriptionRemoveCommand::class, $container->get(SubscriptionRemoveCommand::class));
        self::assertInstanceOf(SubscriptionStatusCommand::class, $container->get(SubscriptionStatusCommand::class));
        self::assertInstanceOf(SubscriptionPauseCommand::class, $container->get(SubscriptionPauseCommand::class));
        self::assertInstanceOf(SubscriptionReactivateCommand::class, $container->get(SubscriptionReactivateCommand::class));
        self::assertInstanceOf(DoctrineCipherKeyStore::class, $container->get(CipherKeyStore::class));
        self::assertInstanceOf(BaseCryptographer::class, $container->get(Cryptographer::class));
    }

    public function testCreateWithReadOnlyStore(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'store' => ['read_only' => true],
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(ReadOnlyStore::class, $container->get(Store::class));
    }

    public function testCreateWithUpcasters(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'hydrator' => [
                'upcasters' => [
                    'before_encoding' => [
                        CallbackUpcaster::forClass(
                            ProfileCreated::class,
                            static function (array $data): array {
                                $data['email'] = $data['mail'];
                                unset($data['mail']);

                                return $data;
                            },
                        ),
                    ],
                    'before_transform' => ['upcaster.lower_email'],
                ],
            ],
            'services' => [
                'upcaster.lower_email' => CallbackUpcaster::forClass(
                    ProfileCreated::class,
                    static function (array $data): array {
                        if (!is_string($data['email'])) {
                            return $data;
                        }

                        $data['email'] = strtolower($data['email']);

                        return $data;
                    },
                ),
            ],
        ];
        $container = Factory::create($configuration);

        $event = $container->get(Hydrator::class)->hydrate(
            ProfileCreated::class,
            ['profileId' => '1', 'mail' => 'Info@Patchlevel.de'],
        );

        self::assertSame('info@patchlevel.de', $event->email->toString());
    }

    public function testUserServiceReplacesDefaultService(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2020-01-01 00:00:00'));
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'services' => [ClockInterface::class => $clock],
        ];
        $container = Factory::create($configuration);

        self::assertSame($clock, $container->get(ClockInterface::class));
    }

    public function testUserServiceReplacesAliasedDefaultService(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2020-01-01 00:00:00'));
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'services' => [SystemClock::class => $clock],
        ];
        $container = Factory::create($configuration);

        self::assertSame($clock, $container->get(ClockInterface::class));
    }

    public function testCustomEventBusService(): void
    {
        $eventBus = new DefaultEventBus(new DefaultConsumer(new AttributeListenerProvider([])));
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'event_bus' => ['type' => 'custom', 'service' => 'app.event_bus'],
            'services' => ['app.event_bus' => $eventBus],
        ];
        $container = Factory::create($configuration);

        self::assertSame($eventBus, $container->get(EventBus::class));
    }

    public function testConnectionIsAliasedForConnectionUrl(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
        ];
        $container = Factory::create($configuration);

        self::assertSame($container->get(Factory::CONNECTION_ID), $container->get(Connection::class));
    }

    public function testRegisterBridges(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'hydrator' => ['default_lazy' => true, 'cryptography' => true, 'lifecycle' => true],
            'subscription' => ['gap_detection' => true],
        ];
        $container = Factory::create($configuration);

        $services = [];
        Factory::registerBridges(
            $container,
            static function (string $id, callable $factory) use (&$services): void {
                $services[$id] = $factory();
            },
        );

        self::assertSame(
            [
                RepositoryManager::class,
                CommandBus::class,
                QueryBus::class,
                Store::class,
                SubscriptionEngine::class,
                SubscriptionStore::class,
                SchemaDirector::class,
                DoctrineSchemaListener::class,
                Connection::class,
                ClockInterface::class,
                EventSerializer::class,
                HeadersSerializer::class,
                EventTagExtractor::class,
                Hydrator::class,
                AggregateRootRegistry::class,
                EventRegistry::class,
                CipherKeyStore::class,
                Cryptographer::class,
            ],
            array_keys($services),
        );
        self::assertSame($container->get(CipherKeyStore::class), $services[CipherKeyStore::class]);
    }

    public function testSnapshotAdapterDefinitions(): void
    {
        $customAdapter = new InMemorySnapshotAdapter();
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'snapshot_stores' => [
                'memory' => new InMemorySnapshotAdapter(),
                'psr6' => ['type' => 'psr6', 'service' => 'app.cache_pool'],
                'psr16' => ['type' => 'psr16', 'service' => 'app.simple_cache'],
                'custom' => ['type' => 'custom', 'service' => 'app.snapshot_adapter'],
            ],
            'services' => [
                'app.cache_pool' => new ArrayAdapter(),
                'app.simple_cache' => new Psr16Cache(new ArrayAdapter()),
                'app.snapshot_adapter' => $customAdapter,
            ],
        ];
        $container = Factory::create($configuration);

        $adapterRepository = $container->get(AdapterRepository::class);

        self::assertInstanceOf(DefaultSnapshotStore::class, $container->get(SnapshotStore::class));
        self::assertInstanceOf(InMemorySnapshotAdapter::class, $adapterRepository->get('memory'));
        self::assertInstanceOf(Psr6SnapshotAdapter::class, $adapterRepository->get('psr6'));
        self::assertInstanceOf(Psr16SnapshotAdapter::class, $adapterRepository->get('psr16'));
        self::assertSame($customAdapter, $adapterRepository->get('custom'));
    }

    public function testCommandHandlers(): void
    {
        $handler = new class () {
            public CreateProfile|null $command = null;

            #[Handle]
            public function handle(CreateProfile $command): void
            {
                $this->command = $command;
            }
        };

        $serviceHandler = new class () {
            public QueryProfile|null $command = null;

            #[Handle]
            public function handle(QueryProfile $command): void
            {
                $this->command = $command;
            }
        };

        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'command_bus' => ['handlers' => [$handler, 'app.command_handler']],
            'services' => ['app.command_handler' => $serviceHandler],
        ];
        $container = Factory::create($configuration);

        $createProfile = new CreateProfile(ProfileId::fromString('1'), 'John');
        $queryProfile = new QueryProfile(ProfileId::fromString('1'));

        $container->get(CommandBus::class)->dispatch($createProfile);
        $container->get(CommandBus::class)->dispatch($queryProfile);

        self::assertSame($createProfile, $handler->command);
        self::assertSame($queryProfile, $serviceHandler->command);
    }

    public function testQueryHandlers(): void
    {
        $handler = new class () {
            #[Answer]
            public function answer(OtherQueryProfile $query): string
            {
                return 'other';
            }
        };

        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'query_bus' => ['handlers' => [$handler, 'app.query_handler']],
            'services' => ['app.query_handler' => new QueryAnsweringProjection()],
        ];
        $container = Factory::create($configuration);

        $queryBus = $container->get(QueryBus::class);

        self::assertSame('found', $queryBus->dispatch(new QueryProfile(ProfileId::fromString('1'))));
        self::assertSame('other', $queryBus->dispatch(new OtherQueryProfile(ProfileId::fromString('1'))));
    }

    public function testCommands(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'store' => ['migrate_to_new_store' => true],
        ];
        $container = Factory::create($configuration);

        self::assertSame(
            [
                'event-sourcing:show',
                'event-sourcing:show-aggregate',
                'event-sourcing:watch',
                'event-sourcing:debug',
                'event-sourcing:database:create',
                'event-sourcing:database:drop',
                'event-sourcing:schema:create',
                'event-sourcing:schema:update',
                'event-sourcing:schema:drop',
                'event-sourcing:subscription:setup',
                'event-sourcing:subscription:boot',
                'event-sourcing:subscription:run',
                'event-sourcing:subscription:teardown',
                'event-sourcing:subscription:remove',
                'event-sourcing:subscription:status',
                'event-sourcing:subscription:pause',
                'event-sourcing:subscription:reactivate',
                'event-sourcing:subscription:refresh',
                'event-sourcing:store:migrate',
                'event-sourcing:migration:diff',
                'event-sourcing:migration:migrate',
                'event-sourcing:migration:current',
                'event-sourcing:migration:execute',
                'event-sourcing:migration:status',
            ],
            array_map(static fn (Command $command): string|null => $command->getName(), Factory::commands($container)),
        );
    }

    public function testRetryStrategyDefinitionWithoutOptions(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'subscription' => [
                'retry_strategies' => [
                    'default' => ['type' => 'clock_based'],
                    'no_retry' => ['type' => 'no_retry'],
                ],
            ],
        ];
        $container = Factory::create($configuration);

        $repository = $container->get(RetryStrategyRepository::class);

        self::assertEquals(
            new ClockBasedRetryStrategy($container->get(ClockInterface::class)),
            $repository->get('default'),
        );
        self::assertInstanceOf(NoRetryStrategy::class, $repository->get('no_retry'));
    }

    public function testCleanerDropsDbalTables(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
        ];
        $container = Factory::create($configuration);

        $connection = $container->get(Connection::class);
        $connection->executeStatement('CREATE TABLE projection_profile (id VARCHAR(36) NOT NULL)');

        $container->get(Cleaner::class)->cleanup(
            new Subscription('profile', cleanupTasks: [new DropTableTask('projection_profile')]),
        );

        self::assertFalse($connection->createSchemaManager()->tablesExist(['projection_profile']));
    }

    public function testStoreMigrationIntoAnotherStreamStore(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'store' => [
                'migrate_to_new_store' => [
                    'type' => Configuration::STORE_DBAL_STREAM,
                    'options' => ['table_name' => 'new_event_store'],
                    'translators' => [new ExcludeEventTranslator([ProfileCreated::class])],
                ],
            ],
        ];
        $container = Factory::create($configuration);

        $newStore = $container->get(Factory::NEW_STORE_ID);

        self::assertInstanceOf(StreamDoctrineDbalStore::class, $newStore);
        self::assertNotSame($container->get(Store::class), $newStore);
        self::assertTrue($container->has(StoreMigrateCommand::class));
    }

    public function testCreateWithTaggableStore(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'store' => ['type' => 'dbal_taggable', 'options' => ['default_stream_name' => 'course']],
            'dcb' => true,
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(TaggableDoctrineDbalStore::class, $container->get(Store::class));
        self::assertInstanceOf(StoreDecisionModelBuilder::class, $container->get(DecisionModelBuilder::class));
        self::assertInstanceOf(StoreEventAppender::class, $container->get(EventAppender::class));
        self::assertInstanceOf(AttributeEventTagExtractor::class, $container->get(EventTagExtractor::class));
    }

    public function testTaggableStoreTagsAggregateEvents(): void
    {
        $event = new #[Event('tagged')]
        class ('1') {
            public function __construct(
                #[EventTag(prefix: 'profile')]
                public string $id,
            ) {
            }
        };

        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'store' => ['type' => 'dbal_taggable'],
            'dcb' => true,
        ];
        $container = Factory::create($configuration);

        $message = $container->get(MessageDecorator::class)(Message::create($event));

        self::assertEquals(new TagsHeader(['profile:1']), $message->header(TagsHeader::class));
    }

    public function testStreamStoreDoesNotTagAggregateEvents(): void
    {
        $event = new #[Event('tagged')]
        class ('1') {
            public function __construct(
                #[EventTag(prefix: 'profile')]
                public string $id,
            ) {
            }
        };

        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
        ];
        $container = Factory::create($configuration);

        $message = $container->get(MessageDecorator::class)(Message::create($event));

        self::assertFalse($message->hasHeader(TagsHeader::class));
        self::assertFalse($container->has(DecisionModelBuilder::class));
        self::assertFalse($container->has(EventAppender::class));
    }

    public function testInMemoryStoreProvidesDecisionModel(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'store' => ['type' => 'in_memory'],
            'dcb' => true,
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(StoreDecisionModelBuilder::class, $container->get(DecisionModelBuilder::class));
        self::assertInstanceOf(StoreEventAppender::class, $container->get(EventAppender::class));
    }

    public function testStoreMigrationIntoTaggableStoreIsPartOfSchema(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'store' => [
                'migrate_to_new_store' => [
                    'type' => Configuration::STORE_DBAL_TAGGABLE,
                    'options' => ['table_name' => 'new_event_store'],
                    'translators' => [new ExtractEventTagTranslator()],
                ],
            ],
        ];
        $container = Factory::create($configuration);

        $container->get(SchemaDirector::class)->create();

        self::assertInstanceOf(TaggableDoctrineDbalStore::class, $container->get(Factory::NEW_STORE_ID));
        self::assertTrue(
            $container->get(Connection::class)->createSchemaManager()->tablesExist(['event_store', 'new_event_store']),
        );
    }

    public function testServiceFactory(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'services' => [
                'app.clock' => static fn (Container $container): FrozenClock => new FrozenClock(
                    $container->get(ClockInterface::class)->now(),
                ),
            ],
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(FrozenClock::class, $container->get('app.clock'));
        self::assertSame($container->get('app.clock'), $container->get('app.clock'));
    }

    public function testCreateWithEventFilteredMessageLoader(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'subscription' => ['event_filtered_message_loader' => true],
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(EventFilteredStoreMessageLoader::class, $container->get(MessageLoader::class));
    }

    public function testEventFilteredMessageLoaderCanNotBeCombinedWithGapDetection(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'subscription' => ['gap_detection' => true, 'event_filtered_message_loader' => true],
        ];

        $this->expectException(InvalidConfiguration::class);

        Factory::create($configuration);
    }

    public function testCreateWithProjectionBuilder(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'store' => ['type' => 'dbal_taggable'],
            'dcb' => true,
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(StoreProjectionBuilder::class, $container->get(ProjectionBuilder::class));
    }

    public function testSubscriptionStreamIsRemovedWithSubscription(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'store' => ['type' => 'in_memory'],
            'subscription' => ['event_emitter' => true],
        ];
        $container = Factory::create($configuration);

        $store = $container->get(Store::class);
        $store->save(
            Message::create(new ProfileVisited(ProfileId::fromString('1')))
                ->withHeader(new StreamNameHeader('subscription_profile')),
        );

        $eventDispatcher = $container->get(Factory::SUBSCRIPTION_EVENT_DISPATCHER_ID);
        self::assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);

        $eventDispatcher->dispatch(new OnSubscriptionRemoved(new Subscription('profile')));

        self::assertSame([], $store->streams());
    }

    public function testSubscriptionStreamIsKeptWithoutEventEmitter(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'store' => ['type' => 'in_memory'],
        ];
        $container = Factory::create($configuration);

        $store = $container->get(Store::class);
        $store->save(
            Message::create(new ProfileVisited(ProfileId::fromString('1')))
                ->withHeader(new StreamNameHeader('subscription_profile')),
        );

        $eventDispatcher = $container->get(Factory::SUBSCRIPTION_EVENT_DISPATCHER_ID);
        self::assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);

        $eventDispatcher->dispatch(new OnSubscriptionRemoved(new Subscription('profile')));

        self::assertSame(['subscription_profile'], $store->streams());
    }

    public function testEventEmitterCanNotBeCombinedWithReadOnlyStore(): void
    {
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'store' => ['read_only' => true],
            'subscription' => ['event_emitter' => true],
        ];

        $this->expectException(InvalidConfiguration::class);

        Factory::create($configuration);
    }

    public function testTaggableStoreConnectionUsesPostgreSQLPlatform(): void
    {
        $configuration = [
            'connection' => ['url' => 'pdo-pgsql://user:secret@localhost/app?serverVersion=16'],
            'store' => ['type' => 'dbal_taggable'],
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(
            PostgreSQLPlatform::class,
            $container->get(Connection::class)->getDatabasePlatform(),
        );
    }

    public function testStoreMigrationIntoTaggableStoreConnectionUsesPostgreSQLPlatform(): void
    {
        $configuration = [
            'connection' => ['url' => 'pdo-pgsql://user:secret@localhost/app?serverVersion=16'],
            'store' => [
                'migrate_to_new_store' => ['type' => Configuration::STORE_DBAL_TAGGABLE],
            ],
        ];
        $container = Factory::create($configuration);

        self::assertInstanceOf(
            PostgreSQLPlatform::class,
            $container->get(Connection::class)->getDatabasePlatform(),
        );
    }

    public function testStreamStoreConnectionUsesDefaultPlatform(): void
    {
        $configuration = [
            'connection' => ['url' => 'pdo-pgsql://user:secret@localhost/app?serverVersion=16'],
        ];
        $container = Factory::create($configuration);

        self::assertNotInstanceOf(
            PostgreSQLPlatform::class,
            $container->get(Connection::class)->getDatabasePlatform(),
        );
    }

    public function testServicesWithInterfaceIdsFromExternalContainer(): void
    {
        $connection = DriverManager::getConnection((new DsnParser())->parse('sqlite3:///:memory:'));
        $clock = new FrozenClock(new DateTimeImmutable('2020-01-01 00:00:00'));
        $logger = new NullLogger();

        $configuration = [
            'connection' => ['service' => Connection::class],
            'clock' => ['service' => ClockInterface::class],
            'logger' => ['service' => LoggerInterface::class],
        ];
        $container = Factory::create(
            $configuration,
            new ArrayContainer([
                Connection::class => $connection,
                ClockInterface::class => $clock,
                LoggerInterface::class => $logger,
            ]),
        );

        self::assertSame($connection, $container->get(Factory::CONNECTION_ID));
        self::assertSame($connection, $container->get(Connection::class));
        self::assertSame($clock, $container->get(ClockInterface::class));
        self::assertSame($logger, $container->get(LoggerInterface::class));
        self::assertInstanceOf(StreamDoctrineDbalStore::class, $container->get(Store::class));
    }

    public function testRegisterBridgesSkipsServicesOfExternalContainer(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2020-01-01 00:00:00'));
        $configuration = [
            'connection' => ['url' => 'sqlite3:///:memory:'],
            'clock' => ['service' => 'app.clock'],
        ];
        $container = Factory::create($configuration, new ArrayContainer(['app.clock' => $clock]));

        $services = [];
        Factory::registerBridges($container, static function (string $id, callable $factory) use (&$services): void {
            $services[$id] = $factory();
        });

        self::assertSame($clock, $container->get(ClockInterface::class));
        self::assertArrayNotHasKey(ClockInterface::class, $services);
        self::assertArrayHasKey(Connection::class, $services);
    }
}
