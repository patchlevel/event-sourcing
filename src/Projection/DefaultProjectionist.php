<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorState;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStateCollection;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStore;
use Patchlevel\EventSourcing\Store\StreamableStore;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

use function sprintf;

final class DefaultProjectionist implements Projectionist
{
    public function __construct(
        private readonly StreamableStore $streamableMessageStore,
        private readonly ProjectorStore $projectorStore,
        private readonly ProjectorRepository $projectorRepository,
        private readonly ProjectorResolver $resolver = new MetadataProjectorResolver(),
    ) {
    }

    public function boot(
        ProjectorCriteria $criteria = new ProjectorCriteria(),
        ?LoggerInterface $logger = null
    ): void {
        $projectorStates = $this->projectorStates()
            ->filterByProjectorStatus(ProjectorStatus::New)
            ->filterByCriteria($criteria);

        foreach ($projectorStates as $projectorState) {
            $projector = $this->projectorRepository->findByProjectorId($projectorState->id());

            if (!$projector) {
                throw new RuntimeException();
            }

            $projectorState->booting();
            $this->projectorStore->saveProjectorState($projectorState);

            $createMethod = $this->resolver->resolveCreateMethod($projector);

            if (!$createMethod) {
                continue;
            }

            try {
                $createMethod();
                $logger?->info(sprintf('%s created', $projectorState->id()->toString()));
            } catch (Throwable $e) {
                $logger?->error(sprintf('%s create error', $projectorState->id()->toString()));
                $logger?->error($e->getMessage());
                $projectorState->error();
                $this->projectorStore->saveProjectorState($projectorState);
            }
        }

        $stream = $this->streamableMessageStore->stream();

        foreach ($stream as $message) {
            foreach ($projectorStates->filterByProjectorStatus(ProjectorStatus::Booting) as $projectorState) {
                $this->handleMessage($message, $projectorState);
            }
        }

        foreach ($projectorStates as $projectorState) {
            $projectorState->active();
            $this->projectorStore->saveProjectorState($projectorState);
        }
    }

    public function run(
        ProjectorCriteria $criteria = new ProjectorCriteria(),
        ?int $limit = null,
        ?LoggerInterface $logger = null
    ): void {
        $projectorStates = $this->projectorStates()
            ->filterByProjectorStatus(ProjectorStatus::Active)
            ->filterByCriteria($criteria);

        $currentPosition = $projectorStates->minProjectorPosition();
        $stream = $this->streamableMessageStore->stream($currentPosition);

        foreach ($stream as $message) {
            foreach ($projectorStates->filterByProjectorStatus(ProjectorStatus::Active) as $projectorState) {
                if ($projectorState->position() > $currentPosition) {
                    continue;
                }

                $this->handleMessage($message, $projectorState);
            }

            $currentPosition++;
        }
    }

    public function teardown(
        ProjectorCriteria $criteria = new ProjectorCriteria(),
        ?LoggerInterface $logger = null
    ): void {
        $projectorStates = $this->projectorStates()->filterByProjectorStatus(ProjectorStatus::Outdated);

        foreach ($projectorStates as $projectorState) {
            $projector = $this->projectorRepository->findByProjectorId($projectorState->id());

            if (!$projector) {
                $logger?->warning('WARNING!!!'); // todo
                continue;
            }

            $dropMethod = $this->resolver->resolveDropMethod($projector);

            if ($dropMethod) {
                try {
                    $dropMethod();
                    $logger?->info(sprintf('%s dropped', $projectorState->id()->toString()));
                } catch (Throwable $e) {
                    $logger?->error(sprintf('%s drop error', $projectorState->id()->toString()));
                    $logger?->error($e->getMessage());
                    $projectorState->error();
                    $this->projectorStore->saveProjectorState($projectorState);

                    continue;
                }
            }

            $this->projectorStore->removeProjectorState($projectorState->id());
        }
    }

    public function remove(
        ProjectorCriteria $criteria = new ProjectorCriteria(),
        ?LoggerInterface $logger = null
    ): void {
        $projectorStates = $this->projectorStates();

        foreach ($projectorStates as $projectorState) {
            $projector = $this->projectorRepository->findByProjectorId($projectorState->id());

            if ($projector) {
                $dropMethod = $this->resolver->resolveDropMethod($projector);

                if ($dropMethod) {
                    try {
                        $dropMethod();
                        $logger?->info(sprintf('%s dropped', $projectorState->id()->toString()));
                    } catch (Throwable $e) {
                        $logger?->warning(sprintf('%s drop error, skipped', $projectorState->id()->toString()));
                        $logger?->error($e->getMessage());
                    }
                }
            }

            $this->projectorStore->removeProjectorState($projectorState->id());
        }
    }

    private function handleMessage(Message $message, ProjectorState $projectorState): void
    {
        $projector = $this->projectorRepository->findByProjectorId($projectorState->id());

        if (!$projector) {
            throw new RuntimeException();
        }

        $handleMethod = $this->resolver->resolveHandleMethod($projector, $message);

        if ($handleMethod) {
            try {
                $handleMethod($message);
            } catch (Throwable $e) {
                $logger?->error(sprintf('%s create error', $projectorState->id()->toString()));
                $logger?->error($e->getMessage());
                $projectorState->error();
                $this->projectorStore->saveProjectorState($projectorState);

                return;
            }
        }

        $projectorState->incrementPosition();
        $this->projectorStore->saveProjectorState($projectorState);
    }

    public function projectorStates(): ProjectorStateCollection
    {
        $projectorsStates = $this->projectorStore->getStateFromAllProjectors();

        foreach ($projectorsStates as $projectorState) {
            $projector = $this->projectorRepository->findByProjectorId($projectorState->id());

            if ($projector) {
                continue;
            }

            $projectorState->outdated();
            $this->projectorStore->saveProjectorState($projectorState);
        }

        foreach ($this->projectorRepository->projectors() as $projector) {
            if ($projectorsStates->has($projector->projectorId())) {
                continue;
            }

            $projectorsStates = $projectorsStates->add(new ProjectorState($projector->projectorId()));
        }

        return $projectorsStates;
    }
}
