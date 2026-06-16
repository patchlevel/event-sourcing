<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\Batch;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchManager;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchNotFound;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(BatchManager::class)]
#[CoversClass(Batch::class)]
final class BatchManagerTest extends TestCase
{
    private function batch(string $subscriptionId): Batch
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        return new Batch(
            new Subscription($subscriptionId),
            new MetadataSubscriberAccessor(
                $subscriber,
                (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
            ),
            new stdClass(),
        );
    }

    public function testAddGetHasRemove(): void
    {
        $manager = new BatchManager();
        $batch = $this->batch('foo');

        self::assertFalse($manager->has('foo'));

        $manager->add($batch);

        self::assertTrue($manager->has('foo'));
        self::assertSame($batch, $manager->get('foo'));

        $manager->remove('foo');

        self::assertFalse($manager->has('foo'));
    }

    public function testGetMissing(): void
    {
        $this->expectException(BatchNotFound::class);

        (new BatchManager())->get('foo');
    }

    public function testAll(): void
    {
        $manager = new BatchManager();
        $foo = $this->batch('foo');
        $bar = $this->batch('bar');

        $manager->add($foo);
        $manager->add($bar);

        self::assertSame([$foo, $bar], $manager->all());
    }

    public function testClear(): void
    {
        $manager = new BatchManager();
        $manager->add($this->batch('foo'));
        $manager->add($this->batch('bar'));

        $manager->clear();

        self::assertFalse($manager->has('foo'));
        self::assertFalse($manager->has('bar'));
        self::assertSame([], $manager->all());
    }
}
