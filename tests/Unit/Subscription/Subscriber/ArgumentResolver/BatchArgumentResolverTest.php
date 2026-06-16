<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolverContext;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\BatchArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\Batch;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchManager;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\TypeInfo\Type;

#[CoversClass(BatchArgumentResolver::class)]
final class BatchArgumentResolverTest extends TestCase
{
    public function testSupport(): void
    {
        $resolver = new BatchArgumentResolver(new BatchManager());

        self::assertTrue(
            $resolver->support(
                new ArgumentMetadata('foo', Type::object(stdClass::class), true),
                ProfileVisited::class,
            ),
        );

        self::assertFalse(
            $resolver->support(
                new ArgumentMetadata('foo', Type::object(stdClass::class)),
                ProfileVisited::class,
            ),
        );
    }

    public function testResolve(): void
    {
        $state = new stdClass();

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
        };

        $batch = new Batch(
            new Subscription('foo'),
            new MetadataSubscriberAccessor(
                $subscriber,
                (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
            ),
            $state,
        );

        $batchManager = new BatchManager();
        $batchManager->add($batch);

        $resolver = new BatchArgumentResolver($batchManager);
        $message = new Message(new ProfileVisited(ProfileId::fromString('1')));

        self::assertSame(
            $state,
            $resolver->resolve(
                new ArgumentMetadata('foo', Type::object(stdClass::class), true),
                new ArgumentResolverContext($message, new Subscription('foo'), new SubscriberMetadata('foo')),
            ),
        );
    }
}
