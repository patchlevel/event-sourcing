<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Attribute\BatchBegin;
use Patchlevel\EventSourcing\Attribute\BatchFlush;
use Patchlevel\EventSourcing\Attribute\BatchRollback;
use Patchlevel\EventSourcing\Attribute\BatchShouldFlush;
use Patchlevel\EventSourcing\Attribute\BatchState;
use Patchlevel\EventSourcing\Attribute\Cleanup;
use Patchlevel\EventSourcing\Attribute\DisableEventEmitting;
use Patchlevel\EventSourcing\Attribute\EnableEventEmittingDuringBoot;
use Patchlevel\EventSourcing\Attribute\OnFailed;
use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\RetryStrategy;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentTypeNotSupported;
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\BatchMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\ClassIsNotASubscriber;
use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateBeginBatchMethod;
use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateCleanupMethod;
use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateFailedMethod;
use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateFlushMethod;
use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateRollbackBatchMethod;
use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateSetupMethod;
use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateShouldFlushMethod;
use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateSubscribeMethod;
use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateTeardownMethod;
use Patchlevel\EventSourcing\Metadata\Subscriber\IncompleteBatchMethods;
use Patchlevel\EventSourcing\Metadata\Subscriber\MixedTeardownAndCleanupMethods;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscribeMethodMetadata;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\TypeInfo\Type;

#[CoversClass(AttributeSubscriberMetadataFactory::class)]
final class AttributeSubscriberMetadataFactoryTest extends TestCase
{
    public function testNotASubscriber(): void
    {
        $this->expectException(ClassIsNotASubscriber::class);

        $subscriber = new class {
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testEmptySubscriber(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertSame([], $metadata->subscribeMethods);
        self::assertNull($metadata->setupMethod);
        self::assertNull($metadata->teardownMethod);
        self::assertSame('foo', $metadata->id);
        self::assertFalse($metadata->enableEventEmittingDuringBoot);
        self::assertFalse($metadata->disableEventEmitting);
    }

    public function testEnableEventEmittingDuringBoot(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        #[EnableEventEmittingDuringBoot]
        class {
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertTrue($metadata->enableEventEmittingDuringBoot);
        self::assertFalse($metadata->disableEventEmitting);
    }

    public function testDisableEventEmitting(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        #[DisableEventEmitting]
        class {
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertTrue($metadata->disableEventEmitting);
        self::assertFalse($metadata->enableEventEmittingDuringBoot);
    }

    public function testProjector(): void
    {
        $subscriber = new #[Projector('foo')]
        class {
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertSame([], $metadata->subscribeMethods);
        self::assertNull($metadata->setupMethod);
        self::assertNull($metadata->teardownMethod);
        self::assertSame('foo', $metadata->id);
        self::assertSame('projector', $metadata->group);
        self::assertSame(RunMode::FromBeginning, $metadata->runMode);
    }

    public function testProcessor(): void
    {
        $subscriber = new #[Processor('foo')]
        class {
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertSame([], $metadata->subscribeMethods);
        self::assertNull($metadata->setupMethod);
        self::assertNull($metadata->teardownMethod);
        self::assertSame('foo', $metadata->id);
        self::assertSame('processor', $metadata->group);
        self::assertSame(RunMode::FromNow, $metadata->runMode);
    }

    public function testStandardSubscriber(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function handle(): void
            {
            }

            #[Setup]
            public function create(): void
            {
            }

            #[Teardown]
            public function drop(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertEquals(
            [
                ProfileVisited::class => new SubscribeMethodMetadata('handle', []),
            ],
            $metadata->subscribeMethods,
        );

        self::assertSame('create', $metadata->setupMethod);
        self::assertSame('drop', $metadata->teardownMethod);
    }

    public function testMultipleHandlerOnOneMethod(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            #[Subscribe(ProfileCreated::class)]
            public function handle(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertEquals(
            [
                ProfileVisited::class => new SubscribeMethodMetadata('handle', []),
                ProfileCreated::class => new SubscribeMethodMetadata('handle', []),
            ],
            $metadata->subscribeMethods,
        );
    }

    public function testSubscribeAll(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(Subscribe::ALL)]
            public function handle(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertEquals(
            [
                '*' => new SubscribeMethodMetadata('handle', []),
            ],
            $metadata->subscribeMethods,
        );
    }

    public function testSubscribeAttributes(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function profileVisited(Message $message): void
            {
            }

            #[Subscribe(ProfileCreated::class)]
            public function profileCreated(ProfileCreated $profileCreated, string $aggregateId): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertEquals(
            [
                ProfileVisited::class => new SubscribeMethodMetadata(
                    'profileVisited',
                    [new ArgumentMetadata('message', Type::object(Message::class))],
                ),

                ProfileCreated::class => new SubscribeMethodMetadata(
                    'profileCreated',
                    [
                        new ArgumentMetadata('profileCreated', Type::object(ProfileCreated::class)),
                        new ArgumentMetadata('aggregateId', Type::string()),
                    ],
                ),
            ],
            $metadata->subscribeMethods,
        );
    }

    public function testSubscribeNullableAttribute(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function profileVisited(ProfileVisited|null $message): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertEquals(
            [
                ProfileVisited::class => new SubscribeMethodMetadata('profileVisited', [
                    new ArgumentMetadata('message', Type::nullable(Type::object(ProfileVisited::class))),
                ]),
            ],
            $metadata->subscribeMethods,
        );
    }

    public function testMissingArgumentType(): void
    {
        $this->expectException(ArgumentTypeNotSupported::class);

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            // phpcs:disable
            #[Subscribe(ProfileVisited::class)]
            public function profileVisited($message): void
            {
            }
            // phpcs:enable
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testSubscribeAllWithExplicitSubscribeMethod(): void
    {
        $this->expectException(DuplicateSubscribeMethod::class);

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function profileVisited(ProfileVisited $event): void
            {
            }

            #[Subscribe(Subscribe::ALL)]
            public function listenAll(ProfileVisited $event): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testSubscribeSameEvent(): void
    {
        $this->expectException(DuplicateSubscribeMethod::class);

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function profileVisited(ProfileVisited $event): void
            {
            }

            #[Subscribe(ProfileVisited::class)]
            public function profileVisitedAgain(ProfileVisited $event): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testDuplicateSetupAttributeException(): void
    {
        $this->expectException(DuplicateSetupMethod::class);

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Setup]
            public function create1(): void
            {
            }

            #[Setup]
            public function create2(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testDuplicateTeardownAttributeException(): void
    {
        $this->expectException(DuplicateTeardownMethod::class);

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Teardown]
            public function drop1(): void
            {
            }

            #[Teardown]
            public function drop2(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testBatchMetadata(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function profileVisited(ProfileVisited $event, #[BatchState]
            object $state,): void
            {
            }

            #[BatchBegin]
            public function begin(): object
            {
                return new stdClass();
            }

            #[BatchFlush(afterMessages: 100)]
            public function flush(object $state): void
            {
            }

            #[BatchShouldFlush]
            public function shouldFlush(object $state): bool
            {
                return false;
            }

            #[BatchRollback]
            public function rollback(object $state): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertEquals(
            new BatchMetadata('flush', 'begin', 'shouldFlush', 'rollback', 100),
            $metadata->batch,
        );

        self::assertTrue($metadata->subscribeMethods[ProfileVisited::class]->arguments[1]->hasAttribute(BatchState::class));
        self::assertFalse($metadata->subscribeMethods[ProfileVisited::class]->arguments[0]->hasAttribute(BatchState::class));
    }

    public function testBatchMinimalMetadata(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[BatchBegin]
            public function begin(): object
            {
                return new stdClass();
            }

            #[BatchFlush]
            public function flush(object $state): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertEquals(new BatchMetadata('flush', 'begin'), $metadata->batch);
    }

    public function testBatchOnlyFlushMethod(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[BatchFlush]
            public function flush(object $state): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertEquals(new BatchMetadata('flush'), $metadata->batch);
    }

    public function testBatchOnlyShouldFlushMethodWithoutFlush(): void
    {
        $this->expectException(IncompleteBatchMethods::class);

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[BatchShouldFlush]
            public function shouldFlush(object $state): bool
            {
                return false;
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testBatchOnlyRollbackMethodWithoutFlush(): void
    {
        $this->expectException(IncompleteBatchMethods::class);

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[BatchRollback]
            public function rollback(object $state): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testBatchWithoutBeginMethod(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function profileVisited(ProfileVisited $event, #[BatchState]
            object $state,): void
            {
            }

            #[BatchFlush]
            public function flush(object $state): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertEquals(new BatchMetadata('flush'), $metadata->batch);
        self::assertNull($metadata->batch?->beginMethod);
    }

    public function testNoBatchMetadata(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();

        self::assertNull($metadataFactory->metadata($subscriber::class)->batch);
    }

    public function testBatchArgumentWithoutLifecycleMethods(): void
    {
        $this->expectException(IncompleteBatchMethods::class);

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function profileVisited(ProfileVisited $event, #[BatchState]
            object $state,): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testBatchWithoutFlushMethod(): void
    {
        $this->expectException(IncompleteBatchMethods::class);
        $this->expectExceptionMessage('uses batching but does not define a method marked with the #[BatchFlush] attribute');

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[BatchBegin]
            public function begin(): object
            {
                return new stdClass();
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testDuplicateBeginBatchException(): void
    {
        $this->expectException(DuplicateBeginBatchMethod::class);
        $this->expectExceptionMessage('have been marked as "begin batch" methods');

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[BatchBegin]
            public function begin1(): object
            {
                return new stdClass();
            }

            #[BatchBegin]
            public function begin2(): object
            {
                return new stdClass();
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testDuplicateFlushException(): void
    {
        $this->expectException(DuplicateFlushMethod::class);
        $this->expectExceptionMessage('have been marked as "flush" methods');

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[BatchFlush]
            public function flush1(object $state): void
            {
            }

            #[BatchFlush]
            public function flush2(object $state): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testDuplicateShouldFlushException(): void
    {
        $this->expectException(DuplicateShouldFlushMethod::class);
        $this->expectExceptionMessage('have been marked as "should flush" methods');

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[BatchShouldFlush]
            public function shouldFlush1(object $state): bool
            {
                return false;
            }

            #[BatchShouldFlush]
            public function shouldFlush2(object $state): bool
            {
                return false;
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testDuplicateRollbackBatchException(): void
    {
        $this->expectException(DuplicateRollbackBatchMethod::class);
        $this->expectExceptionMessage('have been marked as "rollback batch" methods');

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[BatchRollback]
            public function rollback1(object $state): void
            {
            }

            #[BatchRollback]
            public function rollback2(object $state): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testMetadataCache(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();

        self::assertSame(
            $metadataFactory->metadata($subscriber::class),
            $metadataFactory->metadata($subscriber::class),
        );
    }

    public function testOnFailedMethod(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[OnFailed]
            public function onFailed(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertSame('onFailed', $metadata->failedMethod);
    }

    public function testDuplicateFailedException(): void
    {
        $this->expectException(DuplicateFailedMethod::class);
        $this->expectExceptionMessage('have been marked as "OnFailed" methods');

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[OnFailed]
            public function onFailed1(): void
            {
            }

            #[OnFailed]
            public function onFailed2(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testCleanupMethod(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Cleanup]
            public function cleanup(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertSame('cleanup', $metadata->cleanupMethod);
        self::assertNull($metadata->teardownMethod);
    }

    public function testDuplicateCleanupException(): void
    {
        $this->expectException(DuplicateCleanupMethod::class);
        $this->expectExceptionMessage('have been marked as "cleanup" methods');

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Cleanup]
            public function cleanup1(): void
            {
            }

            #[Cleanup]
            public function cleanup2(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testCleanupAfterTeardownException(): void
    {
        $this->expectException(MixedTeardownAndCleanupMethods::class);

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Teardown]
            public function teardown(): void
            {
            }

            #[Cleanup]
            public function cleanup(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testTeardownAfterCleanupException(): void
    {
        $this->expectException(MixedTeardownAndCleanupMethods::class);

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Cleanup]
            public function cleanup(): void
            {
            }

            #[Teardown]
            public function teardown(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testRetryStrategy(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        #[RetryStrategy('custom_strategy')]
        class {
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertSame('custom_strategy', $metadata->retryStrategy);
    }
}
