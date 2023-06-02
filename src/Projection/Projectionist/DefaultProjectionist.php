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
use Patchlevel\EventSourcing\Store\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\Store;
use Psr\Log\LoggerInterface;
use Throwable;

use function sprintf;

final class DefaultProjectionist implements Projectionist
{
    /** @var array<string, VersionedProjector>|null */
    private array|null $projectors = null;

    public function __construct(
        private readonly Store $streamableMessageStore,
        private readonly ProjectionStore $projectionStore,
        private readonly ProjectorRepository $projectorRepository,
        private readonly ProjectorResolver $projectorResolver = new MetadataProjectorResolver(),
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function boot(ProjectionCriteria $criteria = new ProjectionCriteria(), int|null $limit = null): void
    {
        $projections = $this->projections()
            ->filter(static fn (Projection $projection) => $projection->isNew() || $projection->isBooting())
            ->filterByCriteria($criteria);

        foreach ($projections->filterByProjectionStatus(ProjectionStatus::New) as $projection) {
            $projector = $this->projector($projection->id());

            if (!$projector) {
                throw new ProjectorNotFound($projection->id());
            }

            $projection->booting();
            $this->projectionStore->save($projection);

            $this->logger?->info(sprintf('projection "%s" has been set to booting', $projection->id()->toString()));

            $createMethod = $this->projectorResolver->resolveCreateMethod($projector);

            if (!$createMethod) {
                $this->logger?->info(sprintf('projector for "%s" has no create method', $projection->id()->toString()));

                continue;
            }

            try {
                $createMethod();
                $this->logger?->info(sprintf('projection for "%s" prepared', $projection->id()->toString()));
            } catch (Throwable $e) {
                $this->logger?->error(sprintf('preparing error in "%s"', $projection->id()->toString()));
                $this->logger?->error($e->getMessage());
                $projection->error();
                $this->projectionStore->save($projection);
            }
        }

        $projections = $projections->filterByProjectionStatus(ProjectionStatus::Booting);

        if ($projections->count() === 0) {
            $this->logger?->info('no projections to process, finish');

            return;
        }

        $currentPosition = $projections->getLowestProjectionPosition();

        $this->logger?->debug(sprintf('event stream is processed from position %s', $currentPosition));

        $criteria = (new CriteriaBuilder())->fromIndex($currentPosition)->build();
        $stream = $this->streamableMessageStore->load($criteria);

        $messageCounter = 0;

        foreach ($stream as $message) {
            foreach ($projections->filterByProjectionStatus(ProjectionStatus::Booting) as $projection) {
                $this->handleMessage($message, $projection);
            }

            $currentPosition++;
            $messageCounter++;

            $this->logger?->info(sprintf('current event stream position: %s', $currentPosition));

            if ($limit !== null && $messageCounter >= $limit) {
                $this->logger?->info('message limit reached, finish');

                return;
            }
        }

        $this->logger?->info('end of stream has been reached');

        foreach ($projections->filterByProjectionStatus(ProjectionStatus::Booting) as $projection) {
            $projection->active();
            $this->projectionStore->save($projection);

            $this->logger?->info(sprintf('projection "%s" has been set to active', $projection->id()->toString()));
        }

        $this->logger?->info('finish');
    }

    public function run(ProjectionCriteria $criteria = new ProjectionCriteria(), int|null $limit = null): void
    {
        $projections = $this->projections()
            ->filterByProjectionStatus(ProjectionStatus::Active)
            ->filterByCriteria($criteria);

        foreach ($projections as $projection) {
            $projector = $this->projector($projection->id());

            if ($projector) {
                continue;
            }

            $projection->outdated();
            $this->projectionStore->save($projection);

            $this->logger?->info(sprintf('projection "%s" has been marked as outdated', $projection->id()->toString()));
        }

        $projections = $projections->filterByProjectionStatus(ProjectionStatus::Active);

        if ($projections->count() === 0) {
            $this->logger?->info('no projections to process, finish');

            return;
        }

        $currentPosition = $projections->getLowestProjectionPosition();

        $this->logger?->debug(sprintf('event stream is processed from position %s', $currentPosition));

        $criteria = (new CriteriaBuilder())->fromIndex($currentPosition)->build();
        $stream = $this->streamableMessageStore->load($criteria);

        $messageCounter = 0;

        foreach ($stream as $message) {
            foreach ($projections->filterByProjectionStatus(ProjectionStatus::Active) as $projection) {
                if ($projection->position() > $currentPosition) {
                    $this->logger?->debug(
                        sprintf(
                            'projection "%s" is farther than the current position (%s) and will be skipped',
                            $projection->id()->toString(),
                            $projection->position(),
                        ),
                    );

                    continue;
                }

                $this->handleMessage($message, $projection);
            }

            $currentPosition++;
            $messageCounter++;

            $this->logger?->info(sprintf('current event stream position: %s', $currentPosition));

            if ($limit !== null && $messageCounter >= $limit) {
                $this->logger?->info('message limit reached, finish');

                return;
            }
        }

        $this->logger?->debug('end of stream has been reached, finish');
    }

    public function teardown(ProjectionCriteria $criteria = new ProjectionCriteria()): void
    {
        $projections = $this
            ->projections()
            ->filterByProjectionStatus(ProjectionStatus::Outdated)
            ->filterByCriteria($criteria);

        foreach ($projections as $projection) {
            $projector = $this->projector($projection->id());

            if (!$projector) {
                $this->logger?->warning(
                    sprintf('projector with the id "%s" not found, skipped', $projection->id()->toString()),
                );

                continue;
            }

            $dropMethod = $this->projectorResolver->resolveDropMethod($projector);

            if ($dropMethod) {
                try {
                    $dropMethod();
                } catch (Throwable $e) {
                    $this->logger?->error(
                        sprintf('projection for "%s" could not be removed, skipped', $projection->id()->toString()),
                    );
                    $this->logger?->error($e->getMessage());
                    continue;
                }
            }

            $this->projectionStore->remove($projection->id());

            $this->logger?->info(
                sprintf('projection for "%s" removed', $projection->id()->toString()),
            );
        }
    }

    public function remove(ProjectionCriteria $criteria = new ProjectionCriteria()): void
    {
        $projections = $this->projections()->filterByCriteria($criteria);

        foreach ($projections as $projection) {
            $projector = $this->projector($projection->id());

            if (!$projector) {
                $this->projectionStore->remove($projection->id());

                $this->logger?->info(
                    sprintf('projection for "%s" removed without a suitable projector', $projection->id()->toString()),
                );

                continue;
            }

            $dropMethod = $this->projectorResolver->resolveDropMethod($projector);

            if (!$dropMethod) {
                $this->projectionStore->remove($projection->id());

                $this->logger?->info(
                    sprintf('projection for "%s" removed', $projection->id()->toString()),
                );

                continue;
            }

            try {
                $dropMethod();
            } catch (Throwable $e) {
                $this->logger?->error(
                    sprintf(
                        'projection "%s" drop method could not be executed:',
                        $projection->id()->toString(),
                    ),
                );
                $this->logger?->error($e->getMessage());
            }

            $this->projectionStore->remove($projection->id());

            $this->logger?->info(
                sprintf('projection for "%s" removed', $projection->id()->toString()),
            );
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
            $this->projectionStore->save($projection);

            $this->logger?->info(sprintf('projector "%s" is reactivated', $projection->id()->toString()));
        }
    }

    public function projections(): ProjectionCollection
    {
        $projections = $this->projectionStore->all();

        foreach ($this->projectors() as $projector) {
            if ($projections->has($projector->targetProjection())) {
                continue;
            }

            $projections = $projections->add(new Projection($projector->targetProjection()));
        }

        return $projections;
    }

    private function handleMessage(Message $message, Projection $projection): void
    {
        $projector = $this->projector($projection->id());

        if (!$projector) {
            throw new ProjectorNotFound($projection->id());
        }

        $handleMethod = $this->projectorResolver->resolveHandleMethod($projector, $message);

        if ($handleMethod) {
            try {
                $handleMethod($message);

                $this->logger?->debug(
                    sprintf(
                        'projector "%s" processed the event "%s"',
                        $projection->id()->toString(),
                        $message->event()::class,
                    ),
                );
            } catch (Throwable $e) {
                $this->logger?->error(
                    sprintf('projector "%s" could not process the message', $projection->id()->toString()),
                );
                $this->logger?->error($e->getMessage());
                $projection->error();
                $this->projectionStore->save($projection);

                return;
            }
        }

        $projection->incrementPosition();
        $this->projectionStore->save($projection);
    }

    private function projector(ProjectionId $projectorId): Projector|null
    {
        $projectors = $this->projectors();

        return $projectors[$projectorId->toString()] ?? null;
    }

    /** @return array<string, VersionedProjector> */
    private function projectors(): array
    {
        if ($this->projectors === null) {
            $this->projectors = [];

            foreach ($this->projectorRepository->projectors() as $projector) {
                if (!$projector instanceof VersionedProjector) {
                    $this->logger?->debug(
                        sprintf('projector "%s" is not stateful', $projector::class),
                    );

                    continue;
                }

                $this->projectors[$projector->targetProjection()->toString()] = $projector;
            }
        }

        return $this->projectors;
    }
}
