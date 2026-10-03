<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Repository;

use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Subscription\Engine\AlreadyProcessing;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepository;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RunSubscriptionEngineRepository::class)]
final class RunSubscriptionEngineRepositoryTest extends TestCase
{
    public function testLoad(): void
    {
        $profileId = ProfileId::fromString('id1');

        $aggregate = Profile::createProfile(
            $profileId,
            Email::fromString('info@patchlevel.de'),
        );

        $defaultRepository = $this->createMock(Repository::class);
        $defaultRepository->expects($this->once())->method('load')->with($profileId)->willReturn($aggregate);

        $engine = $this->createMock(SubscriptionEngine::class);

        $repository = new RunSubscriptionEngineRepository(
            $defaultRepository,
            $engine,
            ['id1', 'id2'],
            ['group1', 'group2'],
            42,
        );

        self::assertSame($aggregate, $repository->load($profileId));
    }

    public function testHas(): void
    {
        $profileId = ProfileId::fromString('id1');

        $defaultRepository = $this->createMock(Repository::class);
        $defaultRepository->expects($this->once())->method('has')->with($profileId)->willReturn(true);

        $engine = $this->createMock(SubscriptionEngine::class);

        $repository = new RunSubscriptionEngineRepository(
            $defaultRepository,
            $engine,
            ['id1', 'id2'],
            ['group1', 'group2'],
            42,
        );

        self::assertSame(true, $repository->has($profileId));
    }

    public function testSave(): void
    {
        $command = new Run(
            ['id1', 'id2'],
            ['group1', 'group2'],
            42,
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('id1'),
            Email::fromString('info@patchlevel.de'),
        );

        $defaultRepository = $this->createMock(Repository::class);
        $defaultRepository->expects($this->once())->method('save')->with($aggregate);

        $engine = $this->createMock(SubscriptionEngine::class);
        $engine->expects($this->once())->method('execute')->with($command)->willReturn(new ProcessedResult(21));

        $repository = new RunSubscriptionEngineRepository(
            $defaultRepository,
            $engine,
            ['id1', 'id2'],
            ['group1', 'group2'],
            42,
        );

        $repository->save($aggregate);
    }

    public function testSaveWithAlreadyProcessing(): void
    {
        $command = new Run(
            ['id1', 'id2'],
            ['group1', 'group2'],
            42,
        );

        $aggregate = Profile::createProfile(
            ProfileId::fromString('id1'),
            Email::fromString('info@patchlevel.de'),
        );

        $defaultRepository = $this->createMock(Repository::class);
        $defaultRepository->expects($this->once())->method('save')->with($aggregate);

        $engine = $this->createMock(SubscriptionEngine::class);
        $engine->expects($this->once())->method('execute')->with($command)->willThrowException(new AlreadyProcessing());

        $repository = new RunSubscriptionEngineRepository(
            $defaultRepository,
            $engine,
            ['id1', 'id2'],
            ['group1', 'group2'],
            42,
        );

        $repository->save($aggregate);
    }
}
