<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorState;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStateCollection;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStore;
use Patchlevel\EventSourcing\Store\StreamableStore;
use Psr\Log\LoggerInterface;
use Throwable;

use function sprintf;

final class DefaultProjectionist implements Projectionist
{
    public function __construct(
        private readonly StreamableStore $streamableMessageStore,
        private readonly ProjectorStore $projectorStore,
        private readonly ProjectorRepository $projectorRepository,
        private readonly ProjectorResolver $resolver = new MetadataProjectorResolver(),
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function boot(ProjectorCriteria $criteria = new ProjectorCriteria(), ?int $limit = null): void
    {
        $projectorStates = $this->projectorStates()
            ->filter(static fn (ProjectorState $state) => $state->isNew() || $state->isBooting())
            ->filterByCriteria($criteria);

        foreach ($projectorStates->filterByProjectorStatus(ProjectorStatus::New) as $projectorState) {
            $projector = $this->projector($projectorState->id());

            $projectorState->booting();
            $this->projectorStore->saveProjectorState($projectorState);

            $createMethod = $this->resolver->resolveCreateMethod($projector);

            if (!$createMethod) {
                continue;
            }

            try {
                $createMethod();
                $this->logger?->info(sprintf('projection for "%s" prepared', $projectorState->id()->toString()));
            } catch (Throwable $e) {
                $this->logger?->error(sprintf('preparing error in "%s"', $projectorState->id()->toString()));
                $this->logger?->error($e->getMessage());
                $projectorState->error();
                $this->projectorStore->saveProjectorState($projectorState);
            }
        }

        $currentPosition = $projectorStates->minProjectorPosition();
        $stream = $this->streamableMessageStore->stream($currentPosition);

        $messageCounter = 0;

        foreach ($stream as $message) {
            foreach ($projectorStates->filterByProjectorStatus(ProjectorStatus::Booting) as $projectorState) {
                $this->handleMessage($message, $projectorState);
            }

            $currentPosition++;

            $this->logger?->info(sprintf('current cursor position: %s', $currentPosition));

            $messageCounter++;
            if ($limit !== null && $messageCounter >= $limit) {
                return;
            }
        }

        foreach ($projectorStates->filterByProjectorStatus(ProjectorStatus::Booting) as $projectorState) {
            $projectorState->active();
            $this->projectorStore->saveProjectorState($projectorState);
        }
    }

    public function run(ProjectorCriteria $criteria = new ProjectorCriteria(), ?int $limit = null): void
    {
        $projectorStates = $this->projectorStates()
            ->filterByProjectorStatus(ProjectorStatus::Active)
            ->filterByCriteria($criteria);

        $currentPosition = $projectorStates->minProjectorPosition();
        $stream = $this->streamableMessageStore->stream($currentPosition);

        foreach ($projectorStates as $projectorState) {
            $projector = $this->projectorRepository->findByProjectorId($projectorState->id());

            if ($projector) {
                continue;
            }

            $projectorState->outdated();
            $this->projectorStore->saveProjectorState($projectorState);
        }

        $messageCounter = 0;

        foreach ($stream as $message) {
            foreach ($projectorStates->filterByProjectorStatus(ProjectorStatus::Active) as $projectorState) {
                if ($projectorState->position() > $currentPosition) {
                    continue;
                }

                $this->handleMessage($message, $projectorState);
            }

            $currentPosition++;

            $this->logger?->info(sprintf('current cursor position: %s', $currentPosition));

            $messageCounter++;
            if ($limit !== null && $messageCounter >= $limit) {
                return;
            }
        }
    }

    public function teardown(ProjectorCriteria $criteria = new ProjectorCriteria()): void
    {
        $projectorStates = $this
            ->projectorStates()
            ->filterByProjectorStatus(ProjectorStatus::Outdated)
            ->filterByCriteria($criteria);

        foreach ($projectorStates as $projectorState) {
            $projector = $this->projectorRepository->findByProjectorId($projectorState->id());

            if (!$projector) {
                $this->logger?->warning(
                    sprintf('projector with the id "%s" not found', $projectorState->id()->toString())
                );

                continue;
            }

            $dropMethod = $this->resolver->resolveDropMethod($projector);

            if ($dropMethod) {
                try {
                    $dropMethod();
                    $this->logger?->info(
                        sprintf('projection for "%s" removed', $projectorState->id()->toString())
                    );
                } catch (Throwable $e) {
                    $this->logger?->error(sprintf('projection for "%s" could not be removed, skipped', $projectorState->id()->toString()));
                    $this->logger?->error($e->getMessage());
                    continue;
                }
            }

            $this->projectorStore->removeProjectorState($projectorState->id());
        }
    }

    public function remove(ProjectorCriteria $criteria = new ProjectorCriteria()): void
    {
        $projectorStates = $this->projectorStates()->filterByCriteria($criteria);

        foreach ($projectorStates as $projectorState) {
            $projector = $this->projectorRepository->findByProjectorId($projectorState->id());

            if (!$projector) {
                $this->projectorStore->removeProjectorState($projectorState->id());

                continue;
            }

            $dropMethod = $this->resolver->resolveDropMethod($projector);

            if (!$dropMethod) {
                $this->projectorStore->removeProjectorState($projectorState->id());

                continue;
            }

            try {
                $dropMethod();
                $this->logger?->info(
                    sprintf('projection for "%s" removed', $projectorState->id()->toString())
                );
            } catch (Throwable $e) {
                $this->logger?->error(
                    sprintf(
                        'projection for "%s" could not be removed, state was removed',
                        $projectorState->id()->toString()
                    )
                );
                $this->logger?->error($e->getMessage());
            }

            $this->projectorStore->removeProjectorState($projectorState->id());
        }
    }

    public function reactivate(ProjectorCriteria $criteria = new ProjectorCriteria()): void
    {
        $projectorStates = $this
            ->projectorStates()
            ->filterByProjectorStatus(ProjectorStatus::Error)
            ->filterByCriteria($criteria);

        foreach ($projectorStates as $projectorState) {
            $projectorState->active();
            $this->projectorStore->saveProjectorState($projectorState);

            $this->logger?->info(sprintf('projector "%s" is reactivated', $projectorState->id()->toString()));
        }
    }

    public function projectorStates(): ProjectorStateCollection
    {
        $projectorsStates = $this->projectorStore->getStateFromAllProjectors();

        foreach ($this->projectorRepository->projectors() as $projector) {
            if ($projectorsStates->has($projector->projectorId())) {
                continue;
            }

            $projectorsStates = $projectorsStates->add(new ProjectorState($projector->projectorId()));
        }

        return $projectorsStates;
    }

    private function handleMessage(Message $message, ProjectorState $projectorState): void
    {
        $projector = $this->projector($projectorState->id());
        $handleMethod = $this->resolver->resolveHandleMethod($projector, $message);

        if ($handleMethod) {
            try {
                $handleMethod($message);
            } catch (Throwable $e) {
                $this->logger?->error(
                    sprintf('projector "%s" could not process the message', $projectorState->id()->toString())
                );
                $this->logger?->error($e->getMessage());
                $projectorState->error();
                $this->projectorStore->saveProjectorState($projectorState);

                return;
            }
        }

        $projectorState->incrementPosition();
        $this->projectorStore->saveProjectorState($projectorState);
    }

    private function projector(ProjectorId $projectorId): Projector
    {
        $projector = $this->projectorRepository->findByProjectorId($projectorId);

        if (!$projector) {
            throw new ProjectorNotFound($projectorId);
        }

        return $projector;
    }
}
