<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Projection\CompositeProjection;
use Patchlevel\EventSourcing\Projection\StoreProjectionBuilder;
use Patchlevel\EventSourcing\Store\AppendStore;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\IncrementProjection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\SplittingEvent;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\VisitsProjection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoreProjectionBuilder::class)]
final class StoreProjectionBuilderTest extends TestCase
{
    public function testBuild(): void
    {
        $projections = ['count' => new IncrementProjection(0, ['match'])];

        $expectedQuery = (new CompositeProjection($projections))->query();

        $message = Message::create(new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('foo@bar.com'),
        ))->withHeader(new TagsHeader(['match']));

        $store = $this->createMock(AppendStore::class);
        $store
            ->expects($this->once())
            ->method('query')
            ->with($expectedQuery)
            ->willReturn(new Stream([$message, $message]));

        $builder = new StoreProjectionBuilder($store);

        self::assertSame(['count' => 2], $builder->build($projections));
    }

    public function testBuildWithSplitStream(): void
    {
        $store = new InMemoryStore();
        $store->save(
            Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new TagsHeader(['match'])),
            Message::create(new SplittingEvent(Email::fromString('foo@bar.com'), 5))
                ->withHeader(new TagsHeader(['match'])),
            Message::create(new ProfileVisited(ProfileId::fromString('1')))->withHeader(new TagsHeader(['match'])),
        );

        $builder = new StoreProjectionBuilder($store);

        self::assertSame(['visits' => 6], $builder->build(['visits' => new VisitsProjection(['match'])]));
    }
}
