<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\PersonalData;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Cryptography\DoctrineCipherKeyStore;
use Patchlevel\EventSourcing\Cryptography\ExtensionDoctrineCipherKeyStore;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\StoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\PersonalData\Processor\DeletePersonalDataProcessor;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Cryptography\PersonalDataPayloadCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\BaseCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PersonalDataTest extends TestCase
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

    public function testSuccessfulWithEvent(): void
    {
        $cipherKeyStore = new DoctrineCipherKeyStore($this->connection);
        $cryptographer = PersonalDataPayloadCryptographer::createWithOpenssl($cipherKeyStore);

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events'], cryptographer: $cryptographer),
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([
                $store,
                $cipherKeyStore,
            ]),
        );

        $schemaDirector->create();

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');

        $repository->save($profile);

        $profile = $repository->load($profileId);

        self::assertInstanceOf(Profile::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());

        $result = $this->connection->fetchAllAssociative('SELECT * FROM event_store');

        self::assertCount(1, $result);
        self::assertArrayHasKey(0, $result);

        $row = $result[0];

        self::assertStringNotContainsString('John', $row['event_payload']);
    }

    public function testRemoveKeyWithEvent(): void
    {
        $cipherKeyStore = new DoctrineCipherKeyStore($this->connection);
        $cryptographer = PersonalDataPayloadCryptographer::createWithOpenssl($cipherKeyStore);

        $subscriptionStore = new DoctrineSubscriptionStore(
            $this->connection,
        );

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events'], cryptographer: $cryptographer),
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([
                $store,
                $cipherKeyStore,
                $subscriptionStore,
            ]),
        );

        $schemaDirector->create();

        $engine = new DefaultSubscriptionEngine(
            new StoreMessageLoader($store),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([new DeletePersonalDataProcessor($cipherKeyStore)]),
        );

        $engine->setup(skipBooting: true);

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');

        $repository->save($profile);
        $engine->run();

        $profile = $repository->load($profileId);

        self::assertInstanceOf(Profile::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());

        $profile->removePersonalData();
        $repository->save($profile);
        $engine->run();

        $profile = $repository->load($profileId);

        self::assertInstanceOf(Profile::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(2, $profile->playhead());
        self::assertSame('unknown', $profile->name());

        $profile->changeName('hallo');
        $repository->save($profile);

        $profile = $repository->load($profileId);

        self::assertInstanceOf(Profile::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(3, $profile->playhead());
        self::assertSame('hallo', $profile->name());
    }

    public function testRemoveKeyWithEventAndSnapshot(): void
    {
        $cipherKeyStore = new DoctrineCipherKeyStore($this->connection);
        $cryptographer = PersonalDataPayloadCryptographer::createWithOpenssl($cipherKeyStore);

        $subscriptionStore = new DoctrineSubscriptionStore(
            $this->connection,
        );

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events'], cryptographer: $cryptographer),
        );

        $snapshotAdapter = new InMemorySnapshotAdapter();

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            null,
            DefaultSnapshotStore::createDefault(
                ['default' => $snapshotAdapter],
                $cryptographer,
            ),
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([
                $store,
                $cipherKeyStore,
                $subscriptionStore,
            ]),
        );

        $schemaDirector->create();

        $engine = new DefaultSubscriptionEngine(
            new StoreMessageLoader($store),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([new DeletePersonalDataProcessor($cipherKeyStore)]),
        );

        $engine->setup(skipBooting: true);

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');
        $profile->changeName('John 2');

        $repository->save($profile);
        $engine->run();

        $profile = $repository->load($profileId);

        self::assertInstanceOf(Profile::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(2, $profile->playhead());
        self::assertSame('John 2', $profile->name());

        $cipherKeyStore->remove($profileId->toString());

        $profile = $repository->load($profileId);

        self::assertInstanceOf(Profile::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(2, $profile->playhead());
        self::assertSame('unknown', $profile->name());
    }

    public function testWithStackHydrator(): void
    {
        $cipherKeyStore = new ExtensionDoctrineCipherKeyStore($this->connection);
        $extension = new CryptographyExtension(
            BaseCryptographer::createWithOpenssl($cipherKeyStore),
        );

        $eventSerializer = new DefaultEventSerializer(
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
            (new StackHydratorBuilder())
                ->useExtension(new CoreExtension())
                ->useExtension($extension)
                ->build(),
        );

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            $eventSerializer,
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([
                $store,
                $cipherKeyStore,
            ]),
        );

        $schemaDirector->create();

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');

        $repository->save($profile);

        $profile = $repository->load($profileId);

        self::assertInstanceOf(Profile::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());

        $result = $this->connection->fetchAllAssociative('SELECT * FROM event_store');

        self::assertCount(1, $result);
        self::assertArrayHasKey(0, $result);

        $row = $result[0];

        self::assertStringNotContainsString('John', $row['event_payload']);
    }

    public function testWithStackHydratorWithLegacyFallback(): void
    {
        $extensionCipherKeyStore = new ExtensionDoctrineCipherKeyStore($this->connection);
        $legacyCipherKeyStore = new DoctrineCipherKeyStore($this->connection);

        $cryptographer = PersonalDataPayloadCryptographer::createWithOpenssl($legacyCipherKeyStore);

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events'], cryptographer: $cryptographer),
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([
                $store,
                $legacyCipherKeyStore,
                $extensionCipherKeyStore,
            ]),
        );

        $schemaDirector->create();

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');

        $repository->save($profile);

        // switch to new hydrator

        $extension = new CryptographyExtension(
            BaseCryptographer::createWithOpenssl($extensionCipherKeyStore),
            PersonalDataPayloadCryptographer::createWithOpenssl($legacyCipherKeyStore),
        );

        $eventSerializer = new DefaultEventSerializer(
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
            (new StackHydratorBuilder())
                ->useExtension(new CoreExtension())
                ->useExtension($extension)
                ->build(),
        );

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            $eventSerializer,
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
        );

        $repository = $manager->get(Profile::class);
        $profile = $repository->load($profileId);

        self::assertInstanceOf(Profile::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());

        $result = $this->connection->fetchAllAssociative('SELECT * FROM event_store');

        self::assertCount(1, $result);
        self::assertArrayHasKey(0, $result);

        $row = $result[0];

        self::assertStringNotContainsString('John', $row['event_payload']);

        $result = $this->connection->fetchAllAssociative('SELECT * FROM crypto_keys');

        self::assertCount(1, $result);
        self::assertArrayHasKey(0, $result);

        $row = $result[0];

        self::assertEquals($profileId->toString(), $row['subject_id']);

        $result = $this->connection->fetchAllAssociative('SELECT * FROM cryptography_keys');

        self::assertCount(0, $result);

        $profile->changeName('John 2');
        $repository->save($profile);

        $result = $this->connection->fetchAllAssociative('SELECT * FROM cryptography_keys');

        self::assertCount(1, $result);
        self::assertEquals($profileId->toString(), $row['subject_id']);
    }
}
