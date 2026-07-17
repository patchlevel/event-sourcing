<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Projection\CompositeProjection;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\SubQuery;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\IncrementProjection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

final class CompositeProjectionTest extends TestCase
{
    public function testQueryAggregatesSubQueries(): void
    {
        $p1 = new IncrementProjection(0, ['tag:a']);
        $p2 = new IncrementProjection(0, ['tag:b'], 'main');

        $composite = new CompositeProjection([
            'a' => $p1,
            'b' => $p2,
        ]);

        self::assertEquals(new Query(
            new SubQuery(
                ['tag:a'],
                [ProfileCreated::class],
            ),
            new SubQuery(
                ['tag:b'],
                [ProfileCreated::class],
                'main',
            ),
        ), $composite->query());
    }

    public function testInitialStateBuildsMap(): void
    {
        $composite = new CompositeProjection([
            'alpha' => new IncrementProjection(1),
            'beta' => new IncrementProjection(5),
        ]);

        $state = $composite->initialState();

        self::assertSame(['alpha' => 1, 'beta' => 5], $state);
    }

    public function testApplyDelegatesToEachProjection(): void
    {
        $composite = new CompositeProjection([
            'x' => new IncrementProjection(0, ['match']),
            'y' => new IncrementProjection(10, ['match']),
        ]);

        $state = $composite->initialState();

        $message = Message::create(new ProfileCreated(
            ProfileId::fromString('test'),
            Email::fromString('foo@example.com'),
        ))
            ->withHeader(new StreamNameHeader('main'))
            ->withHeader(new TagsHeader(['match']));

        $newState = $composite->apply($state, $message);

        // both should have been incremented by 1
        self::assertSame(1, $newState['x']);
        self::assertSame(11, $newState['y']);
    }

    public function testApplySkipsNonMatchingProjection(): void
    {
        $composite = new CompositeProjection([
            'm' => new IncrementProjection(2, ['match']),
            'n' => new IncrementProjection(3, ['other']),
        ]);

        $state = $composite->initialState();

        $message = Message::create(new ProfileCreated(
            ProfileId::fromString('test'),
            Email::fromString('foo@example.com'),
        ))
            ->withHeader(new StreamNameHeader('main'))
            ->withHeader(new TagsHeader(['match']));

        $newState = $composite->apply($state, $message);

        // 'm' matches and increments; 'n' does not match and stays the same
        self::assertSame(3, $newState['m']);
        self::assertSame(3, $newState['n']);
    }

    public function testQueryWithoutSubQueryProvider(): void
    {
        $projection = $this->createMock(Projection::class);

        $composite = new CompositeProjection([
            'a' => $projection,
            'b' => new IncrementProjection(0, ['tag:b']),
        ]);

        self::assertEquals(new Query(), $composite->query());
    }
}
