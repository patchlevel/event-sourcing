<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Repository;

use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Subscription\Engine\AlreadyProcessing;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepository;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepository */
final class RunSubscriptionEngineRepositoryTest extends TestCase
{
    use ProphecyTrait;

    public function testLoad(): void
    {
        $profileId = ProfileId::fromString('id1');

        $aggregate = Profile::createProfile(
            $profileId,
            Email::fromString('info@patchlevel.de'),
        );

        $defaultRepository = $this->prophesize(Repository::class);
        $defaultRepository->load($profileId)->willReturn($aggregate)->shouldBeCalledOnce();

        $engine = $this->prophesize(SubscriptionEngine::class);

        $repository = new RunSubscriptionEngineRepository(
            $defaultRepository->reveal(),
            $engine->reveal(),
            ['id1', 'id2'],
            ['group1', 'group2'],
            42,
        );

        self::assertSame($aggregate, $repository->load($profileId));
    }

    public function testHas(): void
    {
        $profileId = ProfileId::fromString('id1');

        $defaultRepository = $this->prophesize(Repository::class);
        $defaultRepository->has($profileId)->willReturn(true)->shouldBeCalledOnce();

        $engine = $this->prophesize(SubscriptionEngine::class);

        $repository = new RunSubscriptionEngineRepository(
            $defaultRepository->reveal(),
            $engine->reveal(),
            ['id1', 'id2'],
            ['group1', 'group2'],
            42,
        );

        self::assertSame(true, $repository->has($profileId));
    }

    public function testSave(): void
    {
        $criteria = new SubscriptionEngineCriteria(
            ['id1', 'id2'],
            ['group1', 'group2'],
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('id1'),
            Email::fromString('info@patchlevel.de'),
        );

        $defaultRepository = $this->prophesize(Repository::class);
        $defaultRepository->save($aggregate)->shouldBeCalledOnce();

        $engine = $this->prophesize(SubscriptionEngine::class);
        $engine->run($criteria, 42)->willReturn(new ProcessedResult(21))->shouldBeCalledOnce();

        $repository = new RunSubscriptionEngineRepository(
            $defaultRepository->reveal(),
            $engine->reveal(),
            ['id1', 'id2'],
            ['group1', 'group2'],
            42,
        );

        $repository->save($aggregate);
    }

    public function testSaveWithAlreadyProcessing(): void
    {
        $criteria = new SubscriptionEngineCriteria(
            ['id1', 'id2'],
            ['group1', 'group2'],
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('id1'),
            Email::fromString('info@patchlevel.de'),
        );

        $defaultRepository = $this->prophesize(Repository::class);
        $defaultRepository->save($aggregate)->shouldBeCalledOnce();

        $engine = $this->prophesize(SubscriptionEngine::class);
        $engine->run($criteria, 42)->willThrow(new AlreadyProcessing())->shouldBeCalledOnce();

        $repository = new RunSubscriptionEngineRepository(
            $defaultRepository->reveal(),
            $engine->reveal(),
            ['id1', 'id2'],
            ['group1', 'group2'],
            42,
        );

        $repository->save($aggregate);
    }
}
