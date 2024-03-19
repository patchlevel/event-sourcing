<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\PersonalData;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Cryptography\DefaultEventPayloadCryptographer;
use Patchlevel\EventSourcing\Cryptography\Store\DoctrineCipherKeyStore;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\PersonalData\Processor\DeletePersonalDataProcessor;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
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

    public function testSuccessful(): void
    {
        $cipherKeyStore = new DoctrineCipherKeyStore($this->connection);

        $cryptographer = DefaultEventPayloadCryptographer::createWithOpenssl(
            new AttributeEventMetadataFactory(),
            $cipherKeyStore,
        );

        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events'], cryptographer: $cryptographer),
            DefaultHeadersSerializer::createFromPaths([
                __DIR__ . '/../../../src',
                __DIR__,
            ]),
            'eventstore',
        );

        $eventBus = DefaultEventBus::create();

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventBus,
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

        $profileId = ProfileId::fromString('1');
        $profile = Profile::create($profileId, 'John');

        $repository->save($profile);

        $profile = $repository->load($profileId);

        self::assertInstanceOf(Profile::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());

        $result = $this->connection->fetchAllAssociative('SELECT * FROM eventstore');

        self::assertCount(1, $result);
        self::assertArrayHasKey(0, $result);

        $row = $result[0];

        self::assertStringNotContainsString('John', $row['payload']);
    }

    public function testRemoveKey(): void
    {
        $cipherKeyStore = new DoctrineCipherKeyStore($this->connection);

        $cryptographer = DefaultEventPayloadCryptographer::createWithOpenssl(
            new AttributeEventMetadataFactory(),
            $cipherKeyStore,
        );

        $subscriptionStore = new DoctrineSubscriptionStore(
            $this->connection,
        );

        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events'], cryptographer: $cryptographer),
            DefaultHeadersSerializer::createFromPaths([
                __DIR__ . '/../../../src',
                __DIR__,
            ]),
            'eventstore',
        );

        $eventBus = DefaultEventBus::create();

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventBus,
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
            $store,
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([new DeletePersonalDataProcessor($cipherKeyStore)]),
        );

        $engine->setup(skipBooting: true);

        $profileId = ProfileId::fromString('1');
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
}
