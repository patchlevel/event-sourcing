<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Container;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\CommandBus\InstantRetryCommandBus;
use Patchlevel\EventSourcing\CommandBus\SyncCommandBus;
use Patchlevel\EventSourcing\Console\Command\DebugCommand;
use Patchlevel\EventSourcing\Console\Command\ShowAggregateCommand;
use Patchlevel\EventSourcing\Console\Command\ShowCommand;
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
use Patchlevel\EventSourcing\Container\Factory;
use Patchlevel\EventSourcing\Cryptography\ExtensionDoctrineCipherKeyStore;
use Patchlevel\EventSourcing\EventBus\AttributeListenerProvider;
use Patchlevel\EventSourcing\EventBus\Consumer;
use Patchlevel\EventSourcing\EventBus\DefaultConsumer;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\ListenerProvider;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
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
use Patchlevel\EventSourcing\QueryBus\QueryBus;
use Patchlevel\EventSourcing\QueryBus\SyncQueryBus;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\MessageDecorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;
use Patchlevel\EventSourcing\Serializer\Upcast\UpcasterChain;
use Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\CatchUpSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\GapResolverStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\StoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\ThrowOnErrorSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\Hydrator\Extension\Cryptography\BaseCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\Cipher\CipherKeyFactory;
use Patchlevel\Hydrator\Extension\Cryptography\Cryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\StackHydrator;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class FactoryTest extends TestCase
{
    public function testCreateConnectionUrlContainer(): void
    {
        $configuration = Configuration::createWithConnectionUrl('sqlite3:///:memory:');
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
        self::assertInstanceOf(UpcasterChain::class, $container->get(Upcaster::class));
        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
        self::assertInstanceOf(SyncCommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(SyncQueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(StoreMessageLoader::class, $container->get(MessageLoader::class));
        self::assertFalse($container->has(ListenerProvider::class));
        self::assertFalse($container->has(Consumer::class));
        self::assertFalse($container->has(EventBus::class));
        self::assertFalse($container->has(SnapshotStore::class));
        self::assertFalse($container->has(RetryStrategyRepository::class));
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
        $configuration = Configuration::createWithConnectionService($connection);
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
        self::assertInstanceOf(UpcasterChain::class, $container->get(Upcaster::class));
        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
        self::assertInstanceOf(SyncCommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(SyncQueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(StoreMessageLoader::class, $container->get(MessageLoader::class));
        self::assertFalse($container->has(ListenerProvider::class));
        self::assertFalse($container->has(Consumer::class));
        self::assertFalse($container->has(EventBus::class));
        self::assertFalse($container->has(SnapshotStore::class));
        self::assertFalse($container->has(RetryStrategyRepository::class));
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

    public function testCreateWithDefaultSettings(): void
    {
        $configuration = Configuration::createWithConnectionUrl('sqlite3:///:memory:')
            ->withDefaultSettings();
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
        self::assertInstanceOf(UpcasterChain::class, $container->get(Upcaster::class));
        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
        self::assertInstanceOf(InstantRetryCommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(SyncQueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(GapResolverStoreMessageLoader::class, $container->get(MessageLoader::class));
        self::assertFalse($container->has(ListenerProvider::class));
        self::assertFalse($container->has(Consumer::class));
        self::assertFalse($container->has(EventBus::class));
        self::assertFalse($container->has(SnapshotStore::class));
        self::assertFalse($container->has(RetryStrategyRepository::class));
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
        self::assertInstanceOf(ExtensionDoctrineCipherKeyStore::class, $container->get(CipherKeyStore::class));
        self::assertInstanceOf(BaseCryptographer::class, $container->get(Cryptographer::class));
    }

    public function testCreateWithDefaultSettingsAndThrowOnError(): void
    {
        $configuration = Configuration::createWithConnectionUrl('sqlite3:///:memory:')
            ->withDefaultSettings()
            ->withSubscriptionEngineThrowOnError();
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
        self::assertInstanceOf(UpcasterChain::class, $container->get(Upcaster::class));
        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
        self::assertInstanceOf(InstantRetryCommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(SyncQueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(GapResolverStoreMessageLoader::class, $container->get(MessageLoader::class));
        self::assertFalse($container->has(ListenerProvider::class));
        self::assertFalse($container->has(Consumer::class));
        self::assertFalse($container->has(EventBus::class));
        self::assertFalse($container->has(SnapshotStore::class));
        self::assertFalse($container->has(RetryStrategyRepository::class));
        self::assertInstanceOf(DoctrineSubscriptionStore::class, $container->get(SubscriptionStore::class));
        self::assertInstanceOf(MetadataSubscriberAccessorRepository::class, $container->get(SubscriberAccessorRepository::class));
        self::assertInstanceOf(ThrowOnErrorSubscriptionEngine::class, $container->get(SubscriptionEngine::class));
        self::assertInstanceOf(SubscriptionSetupCommand::class, $container->get(SubscriptionSetupCommand::class));
        self::assertInstanceOf(SubscriptionBootCommand::class, $container->get(SubscriptionBootCommand::class));
        self::assertInstanceOf(SubscriptionRunCommand::class, $container->get(SubscriptionRunCommand::class));
        self::assertInstanceOf(SubscriptionTeardownCommand::class, $container->get(SubscriptionTeardownCommand::class));
        self::assertInstanceOf(SubscriptionRemoveCommand::class, $container->get(SubscriptionRemoveCommand::class));
        self::assertInstanceOf(SubscriptionStatusCommand::class, $container->get(SubscriptionStatusCommand::class));
        self::assertInstanceOf(SubscriptionPauseCommand::class, $container->get(SubscriptionPauseCommand::class));
        self::assertInstanceOf(SubscriptionReactivateCommand::class, $container->get(SubscriptionReactivateCommand::class));
        self::assertInstanceOf(ExtensionDoctrineCipherKeyStore::class, $container->get(CipherKeyStore::class));
        self::assertInstanceOf(BaseCryptographer::class, $container->get(Cryptographer::class));
    }

    public function testCreateWithDefaultSettingsAndCatchUpAndThrowOnError(): void
    {
        $configuration = Configuration::createWithConnectionUrl('sqlite3:///:memory:')
            ->withDefaultSettings()
            ->withSubscriptionEngineCatchUp()
            ->withSubscriptionEngineThrowOnError();
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
        self::assertInstanceOf(UpcasterChain::class, $container->get(Upcaster::class));
        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
        self::assertInstanceOf(InstantRetryCommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(SyncQueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(GapResolverStoreMessageLoader::class, $container->get(MessageLoader::class));
        self::assertFalse($container->has(ListenerProvider::class));
        self::assertFalse($container->has(Consumer::class));
        self::assertFalse($container->has(EventBus::class));
        self::assertFalse($container->has(SnapshotStore::class));
        self::assertFalse($container->has(RetryStrategyRepository::class));
        self::assertInstanceOf(DoctrineSubscriptionStore::class, $container->get(SubscriptionStore::class));
        self::assertInstanceOf(MetadataSubscriberAccessorRepository::class, $container->get(SubscriberAccessorRepository::class));
        self::assertInstanceOf(CatchUpSubscriptionEngine::class, $container->get(SubscriptionEngine::class));
        self::assertInstanceOf(SubscriptionSetupCommand::class, $container->get(SubscriptionSetupCommand::class));
        self::assertInstanceOf(SubscriptionBootCommand::class, $container->get(SubscriptionBootCommand::class));
        self::assertInstanceOf(SubscriptionRunCommand::class, $container->get(SubscriptionRunCommand::class));
        self::assertInstanceOf(SubscriptionTeardownCommand::class, $container->get(SubscriptionTeardownCommand::class));
        self::assertInstanceOf(SubscriptionRemoveCommand::class, $container->get(SubscriptionRemoveCommand::class));
        self::assertInstanceOf(SubscriptionStatusCommand::class, $container->get(SubscriptionStatusCommand::class));
        self::assertInstanceOf(SubscriptionPauseCommand::class, $container->get(SubscriptionPauseCommand::class));
        self::assertInstanceOf(SubscriptionReactivateCommand::class, $container->get(SubscriptionReactivateCommand::class));
        self::assertInstanceOf(ExtensionDoctrineCipherKeyStore::class, $container->get(CipherKeyStore::class));
        self::assertInstanceOf(BaseCryptographer::class, $container->get(Cryptographer::class));
    }

    public function testCreateWithDefaultSettingsAndEventBus(): void
    {
        $configuration = Configuration::createWithConnectionUrl('sqlite3:///:memory:')
            ->withDefaultSettings()
            ->withEventBus();
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
        self::assertInstanceOf(UpcasterChain::class, $container->get(Upcaster::class));
        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
        self::assertInstanceOf(InstantRetryCommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(SyncQueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(GapResolverStoreMessageLoader::class, $container->get(MessageLoader::class));
        self::assertInstanceOf(AttributeListenerProvider::class, $container->get(ListenerProvider::class));
        self::assertInstanceOf(DefaultConsumer::class, $container->get(Consumer::class));
        self::assertInstanceOf(DefaultEventBus::class, $container->get(EventBus::class));
        self::assertFalse($container->has(SnapshotStore::class));
        self::assertFalse($container->has(RetryStrategyRepository::class));
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
        self::assertInstanceOf(ExtensionDoctrineCipherKeyStore::class, $container->get(CipherKeyStore::class));
        self::assertInstanceOf(BaseCryptographer::class, $container->get(Cryptographer::class));
    }

    public function testCreateWithDefaultSettingsAndSnapshots(): void
    {
        $configuration = Configuration::createWithConnectionUrl('sqlite3:///:memory:')
            ->withDefaultSettings()
            ->withSnapshotAdapters(['default' => new InMemorySnapshotAdapter()]);
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
        self::assertInstanceOf(UpcasterChain::class, $container->get(Upcaster::class));
        self::assertInstanceOf(ChainMessageDecorator::class, $container->get(MessageDecorator::class));
        self::assertInstanceOf(SplitStreamDecorator::class, $container->get(SplitStreamDecorator::class));
        self::assertInstanceOf(InstantRetryCommandBus::class, $container->get(CommandBus::class));
        self::assertInstanceOf(SyncQueryBus::class, $container->get(QueryBus::class));
        self::assertInstanceOf(GapResolverStoreMessageLoader::class, $container->get(MessageLoader::class));
        self::assertFalse($container->has(ListenerProvider::class));
        self::assertFalse($container->has(Consumer::class));
        self::assertFalse($container->has(EventBus::class));
        self::assertInstanceOf(DefaultSnapshotStore::class, $container->get(SnapshotStore::class));
        self::assertFalse($container->has(RetryStrategyRepository::class));
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
        self::assertInstanceOf(ExtensionDoctrineCipherKeyStore::class, $container->get(CipherKeyStore::class));
        self::assertInstanceOf(BaseCryptographer::class, $container->get(Cryptographer::class));
    }

    public function testCreateWithDefaultSettingsAndRetryStrategy(): void
    {
        $configuration = Configuration::createWithConnectionUrl('sqlite3:///:memory:')
            ->withDefaultSettings()
            ->withSubscriptionRetryDefaults();
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
        self::assertInstanceOf(UpcasterChain::class, $container->get(Upcaster::class));
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
        self::assertInstanceOf(ExtensionDoctrineCipherKeyStore::class, $container->get(CipherKeyStore::class));
        self::assertInstanceOf(BaseCryptographer::class, $container->get(Cryptographer::class));
    }
}
