<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionError;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use Patchlevel\EventSourcing\Projection\Projection\Store\ProjectionStore;
use Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorResolver;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorResolver;
use Patchlevel\EventSourcing\Store\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\Store;
use Psr\Log\LoggerInterface;
use Throwable;

use function sprintf;

final class DefaultProjectionist implements Projectionist
{
    private const RETRY_LIMIT = 5;

    /** @var array<string, object>|null */
    private array|null $projectors = null;

    public function __construct(
        private readonly Store $streamableMessageStore,
        private readonly ProjectionStore $projectionStore,
        private readonly ProjectorRepository $projectorRepository,
        private readonly ProjectorResolver $projectorResolver = new MetadataProjectorResolver(),
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function boot(
        ProjectionCriteria $criteria = new ProjectionCriteria(),
        int|null $limit = null,
        bool $throwByError = false,
    ): void {
        $projections = $this->projections()
            ->filter(static fn (Projection $projection) => $projection->isNew() || $projection->isBooting())
            ->filterByCriteria($criteria);

        foreach ($projections->filterByProjectionStatus(ProjectionStatus::New) as $projection) {
            $projector = $this->projector($projection->id());

            if (!$projector) {
                throw ProjectorNotFound::forProjectionId($projection->id());
            }

            $projection->booting();
            $this->projectionStore->save($projection);

            $this->logger?->info(sprintf(
                'projector "%s" for "%s" has been set to booting',
                $projector::class,
                $projection->id()->toString(),
            ));

            $setupMethod = $this->projectorResolver->resolveSetupMethod($projector);

            if (!$setupMethod) {
                $this->logger?->info(sprintf(
                    'projector "%s" for "%s" has no "setup" method',
                    $projector::class,
                    $projection->id()->toString(),
                ));

                continue;
            }

            try {
                $setupMethod();
                $this->logger?->info(sprintf(
                    'projector "%s" for "%s" prepared',
                    $projector::class,
                    $projection->id()->toString(),
                ));
            } catch (Throwable $e) {
                $this->logger?->error(sprintf(
                    'preparing error in "%s" for "%s": %s',
                    $projector::class,
                    $projection->id()->toString(),
                    $e->getMessage(),
                ));

                if ($throwByError) {
                    throw new ProjectionistError(
                        $projector::class,
                        $projection->id(),
                        $e,
                    );
                }

                $projection->error(ProjectionError::fromThrowable($e));
                $projection->disallowRetry();
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

        $stream = null;

        try {
            $criteria = (new CriteriaBuilder())->fromIndex($currentPosition)->build();
            $stream = $this->streamableMessageStore->load($criteria);

            $messageCounter = 0;

            foreach ($stream as $message) {
                foreach ($projections->filterByProjectionStatus(ProjectionStatus::Booting) as $projection) {
                    $this->handleMessage($message, $projection, $throwByError);
                }

                $currentPosition++;
                $messageCounter++;

                $this->logger?->info(sprintf('current event stream position: %s', $currentPosition));

                if ($limit !== null && $messageCounter >= $limit) {
                    $this->logger?->info('message limit reached, finish');

                    return;
                }
            }
        } finally {
            $stream?->close();
        }

        $this->logger?->info('end of stream has been reached');

        foreach ($projections->filterByProjectionStatus(ProjectionStatus::Booting) as $projection) {
            $projection->active();
            $this->projectionStore->save($projection);

            $this->logger?->info(sprintf(
                'projection "%s" has been set to active',
                $projection->id()->toString(),
            ));
        }

        $this->logger?->info('finish');
    }

    public function run(
        ProjectionCriteria $criteria = new ProjectionCriteria(),
        int|null $limit = null,
        bool $throwByError = false,
    ): void {
        $projections = $this->projections()->filterByCriteria($criteria);

        $this->handleOutdatedProjections($projections);
        $this->handleRetryProjections($projections);

        $projections = $projections->filterByProjectionStatus(ProjectionStatus::Active);

        if ($projections->count() === 0) {
            $this->logger?->info('no projections to process, finish');

            return;
        }

        $currentPosition = $projections->getLowestProjectionPosition();

        $this->logger?->debug(sprintf('event stream is processed from position %s', $currentPosition));

        $stream = null;

        try {
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

                    $this->handleMessage($message, $projection, $throwByError);
                }

                $currentPosition++;
                $messageCounter++;

                $this->logger?->info(sprintf('current event stream position: %s', $currentPosition));

                if ($limit !== null && $messageCounter >= $limit) {
                    $this->logger?->info('message limit reached, finish');

                    return;
                }
            }
        } finally {
            $stream?->close();
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
                    sprintf('projector for "%s" not found, skipped', $projection->id()->toString()),
                );

                continue;
            }

            $teardownMethod = $this->projectorResolver->resolveTeardownMethod($projector);

            if ($teardownMethod) {
                try {
                    $teardownMethod();
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
                    sprintf('projection "%s" removed without a suitable projector', $projection->id()->toString()),
                );

                continue;
            }

            $teardownMethod = $this->projectorResolver->resolveTeardownMethod($projector);

            if (!$teardownMethod) {
                $this->projectionStore->remove($projection->id());

                $this->logger?->info(
                    sprintf('projection "%s" removed', $projection->id()->toString()),
                );

                continue;
            }

            try {
                $teardownMethod();
            } catch (Throwable $e) {
                $this->logger?->error(
                    sprintf(
                        'projector "%s" teardown method could not be executed:',
                        $projector::class,
                    ),
                );
                $this->logger?->error($e->getMessage());
            }

            $this->projectionStore->remove($projection->id());

            $this->logger?->info(
                sprintf('projection "%s" removed', $projection->id()->toString()),
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
            $projector = $this->projector($projection->id());

            if (!$projector) {
                $this->logger?->info(
                    sprintf('projector for "%s" not found, skipped', $projection->id()->toString()),
                );

                continue;
            }

            $projection->active();
            $this->projectionStore->save($projection);

            $this->logger?->info(sprintf(
                'projector "%s" for "%s" is reactivated',
                $projector::class,
                $projection->id()->toString(),
            ));
        }
    }

    public function projections(): ProjectionCollection
    {
        $projections = $this->projectionStore->all();
        $projectors = $this->projectors();

        foreach ($projectors as $projector) {
            $projectorId = $this->projectorResolver->projectorId($projector);
            $projectionId = new ProjectionId($projectorId->name(), $projectorId->version());

            if ($projections->has($projectionId)) {
                continue;
            }

            $projections = $projections->add(new Projection($projectionId));
        }

        return $projections;
    }

    private function handleMessage(Message $message, Projection $projection, bool $throwByError): void
    {
        $projector = $this->projector($projection->id());

        if (!$projector) {
            throw ProjectorNotFound::forProjectionId($projection->id());
        }

        $subscribeMethod = $this->projectorResolver->resolveSubscribeMethod($projector, $message);

        if (!$subscribeMethod) {
            $projection->incrementPosition();
            $this->projectionStore->save($projection);

            return;
        }

        try {
            $subscribeMethod($message);

            $this->logger?->debug(
                sprintf(
                    'projector "%s" for "%s" processed the event "%s"',
                    $projector::class,
                    $projection->id()->toString(),
                    $message->event()::class,
                ),
            );
        } catch (Throwable $e) {
            $this->logger?->error(
                sprintf(
                    'projector "%s" for "%s" could not process the event: %s',
                    $projector::class,
                    $projection->id()->toString(),
                    $e->getMessage(),
                ),
            );

            if ($throwByError) {
                throw new ProjectionistError(
                    $projector::class,
                    $projection->id(),
                    $e,
                );
            }

            $projection->error(ProjectionError::fromThrowable($e));
            $projection->incrementRetry();
            $this->projectionStore->save($projection);

            return;
        }

        $projection->incrementPosition();
        $projection->resetRetry();
        $this->projectionStore->save($projection);
    }

    private function projector(ProjectionId $projectorId): object|null
    {
        $projectors = $this->projectors();

        return $projectors[$projectorId->toString()] ?? null;
    }

    /** @return array<string, object> */
    private function projectors(): array
    {
        if ($this->projectors === null) {
            $this->projectors = [];

            foreach ($this->projectorRepository->projectors() as $projector) {
                $projectorId = $this->projectorResolver->projectorId($projector);

                $this->projectors[$projectorId->toString()] = $projector;
            }
        }

        return $this->projectors;
    }

    private function handleOutdatedProjections(ProjectionCollection $projections): void
    {
        foreach ($projections as $projection) {
            if ($projection->isRetryDisallowed()) {
                continue;
            }

            if (!$projection->isActive() && !$projection->isError()) {
                continue;
            }

            $projector = $this->projector($projection->id());

            if ($projector) {
                continue;
            }

            $projection->outdated();
            $this->projectionStore->save($projection);

            $this->logger?->info(sprintf('projection "%s" has been marked as outdated', $projection->id()->toString()));
        }
    }

    private function handleRetryProjections(ProjectionCollection $projections): void
    {
        foreach ($projections->filterByProjectionStatus(ProjectionStatus::Error) as $projection) {
            if ($projection->retry() >= self::RETRY_LIMIT) {
                continue;
            }

            $projection->active();
            $this->projectionStore->save($projection);

            $this->logger?->info(sprintf('retry projection "%s"', $projection->id()->toString()));
        }
    }
}
