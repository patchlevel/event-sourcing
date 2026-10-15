<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\DecisionModel;

use Patchlevel\EventSourcing\DecisionModel\DecisionFactory;
use Patchlevel\EventSourcing\DecisionModel\EventAppender;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DecisionFactory::class)]
final class DecisionFactoryTest extends TestCase
{
    public function testBeginReturnsNewDecision(): void
    {
        $factory = new DecisionFactory(new InMemoryStore());

        self::assertNotSame($factory->begin(), $factory->begin());
    }

    public function testUsesEventAppender(): void
    {
        $event = new ProfileVisited(ProfileId::fromString('1'));

        $eventAppender = $this->createMock(EventAppender::class);
        $eventAppender->expects($this->once())->method('append')->with([$event], null, null);

        $factory = new DecisionFactory(new InMemoryStore(), $eventAppender);
        $factory->begin()->append([$event]);
    }
}
