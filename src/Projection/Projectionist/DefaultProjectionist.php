<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use Patchlevel\EventSourcing\Projection\Projection\Store\ProjectionStore;
use Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorResolver;
use Patchlevel\EventSourcing\Projection\Projector\Projector;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorResolver;
use Patchlevel\EventSourcing\Store\StreamableStore;
use Psr\Log\LoggerInterface;
use Throwable;

use function sprintf;

final class DefaultProjectionist implements Projectionist
{
    public function __construct(
        private readonly StreamableStore $streamableMessageStore,
        private readonly ProjectionStore $projectorStore,
        private readonly ProjectorRepository $projectorRepository,
        private readonly ProjectorResolver $resolver = new MetadataProjectorResolver(),
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function boot(ProjectionCriteria $criteria = new ProjectionCriteria(), ?int $limit = null): void
    {
        $projections = $this->projections()
            ->filter(static fn (Projection $projection) => $projection->isNew() || $projection->isBooting())
            ->filterByCriteria($criteria);

        foreach ($projections->filterByProjectionStatus(ProjectionStatus::New) as $projection) {
            $projector = $this->projector($projection->id());

            $projection->booting();
            $this->projectorStore->save($projection);

            $createMethod = $this->resolver->resolveCreateMethod($projector);

            if (!$createMethod) {
                continue;
            }

            try {
                $createMethod();
                $this->logger?->info(sprintf('projection for "%s" prepared', $projection->id()->toString()));
            } catch (Throwable $e) {
                $this->logger?->error(sprintf('preparing error in "%s"', $projection->id()->toString()));
                $this->logger?->error($e->getMessage());
                $projection->error();
                $this->projectorStore->save($projection);
            }
        }

        $currentPosition = $projections->minProjectionPosition();
        $stream = $this->streamableMessageStore->stream($currentPosition);

        $messageCounter = 0;

        foreach ($stream as $message) {
            foreach ($projections->filterByProjectionStatus(ProjectionStatus::Booting) as $projection) {
                $this->handleMessage($message, $projection);
            }

            $currentPosition++;

            $this->logger?->info(sprintf('current cursor position: %s', $currentPosition));

            $messageCounter++;
            if ($limit !== null && $messageCounter >= $limit) {
                return;
            }
        }

        foreach ($projections->filterByProjectionStatus(ProjectionStatus::Booting) as $projection) {
            $projection->active();
            $this->projectorStore->save($projection);
        }
    }

    public function run(ProjectionCriteria $criteria = new ProjectionCriteria(), ?int $limit = null): void
    {
        $projections = $this->projections()
            ->filterByProjectionStatus(ProjectionStatus::Active)
            ->filterByCriteria($criteria);

        $currentPosition = $projections->minProjectionPosition();
        $stream = $this->streamableMessageStore->stream($currentPosition);

        foreach ($projections as $projection) {
            $projector = $this->projectorRepository->findByProjectionId($projection->id());

            if ($projector) {
                continue;
            }

            $projection->outdated();
            $this->projectorStore->save($projection);
        }

        $messageCounter = 0;

        foreach ($stream as $message) {
            foreach ($projections->filterByProjectionStatus(ProjectionStatus::Active) as $projection) {
                if ($projection->position() > $currentPosition) {
                    continue;
                }

                $this->handleMessage($message, $projection);
            }

            $currentPosition++;

            $this->logger?->info(sprintf('current cursor position: %s', $currentPosition));

            $messageCounter++;
            if ($limit !== null && $messageCounter >= $limit) {
                return;
            }
        }
    }

    public function teardown(ProjectionCriteria $criteria = new ProjectionCriteria()): void
    {
        $projections = $this
            ->projections()
            ->filterByProjectionStatus(ProjectionStatus::Outdated)
            ->filterByCriteria($criteria);

        foreach ($projections as $projection) {
            $projector = $this->projectorRepository->findByProjectionId($projection->id());

            if (!$projector) {
                $this->logger?->warning(
                    sprintf('projector with the id "%s" not found', $projection->id()->toString())
                );

                continue;
            }

            $dropMethod = $this->resolver->resolveDropMethod($projector);

            if ($dropMethod) {
                try {
                    $dropMethod();
                    $this->logger?->info(
                        sprintf('projection for "%s" removed', $projection->id()->toString())
                    );
                } catch (Throwable $e) {
                    $this->logger?->error(
                        sprintf('projection for "%s" could not be removed, skipped', $projection->id()->toString())
                    );
                    $this->logger?->error($e->getMessage());
                    continue;
                }
            }

            $this->projectorStore->remove($projection->id());
        }
    }

    public function remove(ProjectionCriteria $criteria = new ProjectionCriteria()): void
    {
        $projections = $this->projections()->filterByCriteria($criteria);

        foreach ($projections as $projection) {
            $projector = $this->projectorRepository->findByProjectionId($projection->id());

            if (!$projector) {
                $this->projectorStore->remove($projection->id());

                continue;
            }

            $dropMethod = $this->resolver->resolveDropMethod($projector);

            if (!$dropMethod) {
                $this->projectorStore->remove($projection->id());

                continue;
            }

            try {
                $dropMethod();
                $this->logger?->info(
                    sprintf('projection for "%s" removed', $projection->id()->toString())
                );
            } catch (Throwable $e) {
                $this->logger?->error(
                    sprintf(
                        'projection for "%s" could not be removed, state was removed',
                        $projection->id()->toString()
                    )
                );
                $this->logger?->error($e->getMessage());
            }

            $this->projectorStore->remove($projection->id());
        }
    }

    public function reactivate(ProjectionCriteria $criteria = new ProjectionCriteria()): void
    {
        $projections = $this
            ->projections()
            ->filterByProjectionStatus(ProjectionStatus::Error)
            ->filterByCriteria($criteria);

        foreach ($projections as $projection) {
            $projection->active();
            $this->projectorStore->save($projection);

            $this->logger?->info(sprintf('projector "%s" is reactivated', $projection->id()->toString()));
        }
    }

    public function projections(): ProjectionCollection
    {
        $projectorsStates = $this->projectorStore->all();

        foreach ($this->projectorRepository->statefulProjectors() as $projector) {
            if ($projectorsStates->has($projector->projectionId())) {
                continue;
            }

            $projectorsStates = $projectorsStates->add(new Projection($projector->projectionId()));
        }

        return $projectorsStates;
    }

    private function handleMessage(Message $message, Projection $projection): void
    {
        $projector = $this->projector($projection->id());
        $handleMethod = $this->resolver->resolveHandleMethod($projector, $message);

        if ($handleMethod) {
            try {
                $handleMethod($message);
            } catch (Throwable $e) {
                $this->logger?->error(
                    sprintf('projector "%s" could not process the message', $projection->id()->toString())
                );
                $this->logger?->error($e->getMessage());
                $projection->error();
                $this->projectorStore->save($projection);

                return;
            }
        }

        $projection->incrementPosition();
        $this->projectorStore->save($projection);
    }

    private function projector(ProjectionId $projectorId): Projector
    {
        $projector = $this->projectorRepository->findByProjectionId($projectorId);

        if (!$projector) {
            throw new ProjectorNotFound($projectorId);
        }

        return $projector;
    }
}
