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
use Patchlevel\EventSourcing\Store\Criteria;
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
        $this->logger?->info(
            'Projectionist: Start booting.',
        );

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
                'Projectionist: New Projector "%s" for "%s" was found and is now booting.',
                $projector::class,
                $projection->id()->toString(),
            ));

            $setupMethod = $this->projectorResolver->resolveSetupMethod($projector);

            if (!$setupMethod) {
                $this->logger?->debug(sprintf(
                    'Projectionist: Projector "%s" for "%s" has no setup method, continue.',
                    $projector::class,
                    $projection->id()->toString(),
                ));

                continue;
            }

            try {
                $setupMethod();
                $this->logger?->debug(sprintf(
                    'Projectionist: For Projector "%s" for "%s" the setup method has been executed and is now prepared for data.',
                    $projector::class,
                    $projection->id()->toString(),
                ));
            } catch (Throwable $e) {
                $this->logger?->error(sprintf(
                    'Projectionist: Projector "%s" for "%s" has an error in the setup method: %s',
                    $projector::class,
                    $projection->id()->toString(),
                    $e->getMessage(),
                ));

                $projection->error(ProjectionError::fromThrowable($e));
                $projection->disallowRetry();
                $this->projectionStore->save($projection);

                if ($throwByError) {
                    throw new ProjectionistError(
                        $projector::class,
                        $projection->id(),
                        $e,
                    );
                }
            }
        }

        $projections = $projections->filterByProjectionStatus(ProjectionStatus::Booting);

        if ($projections->count() === 0) {
            $this->logger?->info('Projectionist: No projections in booting status, finish booting.');

            return;
        }

        $startIndex = $projections->getLowestProjectionPosition();

        $this->logger?->debug(
            sprintf(
                'Projectionist: Event stream is processed for booting from position %s.',
                $startIndex,
            ),
        );

        $stream = null;

        try {
            $criteria = new Criteria(fromIndex: $startIndex);
            $stream = $this->streamableMessageStore->load($criteria);

            $messageCounter = 0;

            foreach ($stream as $message) {
                $index = $stream->index();

                if ($index === null) {
                    throw new UnexpectedError('Stream index is null, this should not happen.');
                }

                foreach ($projections->filterByProjectionStatus(ProjectionStatus::Booting) as $projection) {
                    if ($projection->position() >= $index) {
                        $this->logger?->debug(
                            sprintf(
                                'Projectionist: Projection "%s" is farther than the current position (%d > %d), continue booting.',
                                $projection->id()->toString(),
                                $projection->position(),
                                $index,
                            ),
                        );

                        continue;
                    }

                    $this->handleMessage($index, $message, $projection, $throwByError);
                }

                $messageCounter++;

                $this->logger?->debug(
                    sprintf(
                        'Projectionist: Current event stream position for booting: %s',
                        $index,
                    ),
                );

                if ($limit !== null && $messageCounter >= $limit) {
                    $this->logger?->info(
                        sprintf(
                            'Projectionist: Message limit (%d) reached, finish booting.',
                            $limit,
                        ),
                    );

                    return;
                }
            }
        } finally {
            $stream?->close();
        }

        $this->logger?->debug('Projectionist: End of stream for booting has been reached.');

        foreach ($projections->filterByProjectionStatus(ProjectionStatus::Booting) as $projection) {
            $projection->active();
            $this->projectionStore->save($projection);

            $this->logger?->info(sprintf(
                'Projectionist: Projection "%s" has been set to active after booting.',
                $projection->id()->toString(),
            ));
        }

        $this->logger?->info('Projectionist: Finish booting.');
    }

    public function run(
        ProjectionCriteria $criteria = new ProjectionCriteria(),
        int|null $limit = null,
        bool $throwByError = false,
    ): void {
        $this->logger?->info('Projectionist: Start processing.');

        $projections = $this->projections()->filterByCriteria($criteria);

        $this->handleOutdatedProjections($projections);
        $this->handleRetryProjections($projections);

        $projections = $projections->filterByProjectionStatus(ProjectionStatus::Active);

        if ($projections->count() === 0) {
            $this->logger?->info('Projectionist: No projections to process, finish processing.');

            return;
        }

        $startIndex = $projections->getLowestProjectionPosition();

        $this->logger?->debug(
            sprintf(
                'Projectionist: Event stream is processed from position %d.',
                $startIndex,
            ),
        );

        $stream = null;

        try {
            $criteria = new Criteria(fromIndex: $startIndex);
            $stream = $this->streamableMessageStore->load($criteria);

            $messageCounter = 0;

            foreach ($stream as $message) {
                $index = $stream->index();

                if ($index === null) {
                    throw new UnexpectedError('Stream index is null, this should not happen.');
                }

                foreach ($projections->filterByProjectionStatus(ProjectionStatus::Active) as $projection) {
                    if ($projection->position() >= $index) {
                        $this->logger?->debug(
                            sprintf(
                                'Projectionist: Projection "%s" is farther than the current position (%d > %d), continue processing.',
                                $projection->id()->toString(),
                                $projection->position(),
                                $index,
                            ),
                        );

                        continue;
                    }

                    $this->handleMessage($index, $message, $projection, $throwByError);
                }

                $messageCounter++;

                $this->logger?->debug(sprintf('Projectionist: Current event stream position: %s', $index));

                if ($limit !== null && $messageCounter >= $limit) {
                    $this->logger?->info(
                        sprintf(
                            'Projectionist: Message limit (%d) reached, finish processing.',
                            $limit,
                        ),
                    );

                    return;
                }
            }
        } finally {
            $stream?->close();
        }

        $this->logger?->info(
            sprintf(
                'Projectionist: End of stream on position "%d" has been reached, finish processing.',
                $stream->index() ?: 'unknown',
            ),
        );
    }

    public function teardown(ProjectionCriteria $criteria = new ProjectionCriteria()): void
    {
        $this->logger?->info('Projectionist: Start teardown outdated projections.');

        $projections = $this
            ->projections()
            ->filterByProjectionStatus(ProjectionStatus::Outdated)
            ->filterByCriteria($criteria);

        foreach ($projections as $projection) {
            $projector = $this->projector($projection->id());

            if (!$projector) {
                $this->logger?->warning(
                    sprintf(
                        'Projectionist: Projector for "%s" to teardown not found, skipped.',
                        $projection->id()->toString(),
                    ),
                );

                continue;
            }

            $teardownMethod = $this->projectorResolver->resolveTeardownMethod($projector);

            if (!$teardownMethod) {
                $this->projectionStore->remove($projection->id());

                $this->logger?->info(
                    sprintf(
                        'Projectionist: Projector "%s" for "%s" has no teardown method and was immediately removed.',
                        $projector::class,
                        $projection->id()->toString(),
                    ),
                );

                continue;
            }

            try {
                $teardownMethod();

                $this->logger?->debug(sprintf(
                    'Projectionist: For Projector "%s" for "%s" the teardown method has been executed and is now prepared to be removed.',
                    $projector::class,
                    $projection->id()->toString(),
                ));
            } catch (Throwable $e) {
                $this->logger?->error(
                    sprintf(
                        'Projectionist: Projection "%s" for "%s" has an error in the teardown method, skipped: %s',
                        $projector::class,
                        $projection->id()->toString(),
                        $e->getMessage(),
                    ),
                );
                continue;
            }

            $this->projectionStore->remove($projection->id());

            $this->logger?->info(
                sprintf(
                    'Projectionist: Projection "%s" removed.',
                    $projection->id()->toString(),
                ),
            );
        }

        $this->logger?->info('Projectionist: Finish teardown.');
    }

    public function remove(ProjectionCriteria $criteria = new ProjectionCriteria()): void
    {
        $projections = $this->projections()->filterByCriteria($criteria);

        foreach ($projections as $projection) {
            $projector = $this->projector($projection->id());

            if (!$projector) {
                $this->projectionStore->remove($projection->id());

                $this->logger?->info(
                    sprintf('Projectionist: Projection "%s" removed without a suitable projector.', $projection->id()->toString()),
                );

                continue;
            }

            $teardownMethod = $this->projectorResolver->resolveTeardownMethod($projector);

            if (!$teardownMethod) {
                $this->projectionStore->remove($projection->id());

                $this->logger?->info(
                    sprintf('Projectionist: Projection "%s" removed.', $projection->id()->toString()),
                );

                continue;
            }

            try {
                $teardownMethod();
            } catch (Throwable $e) {
                $this->logger?->error(
                    sprintf(
                        'Projectionist: Projector "%s" teardown method could not be executed: %s',
                        $projector::class,
                        $e->getMessage(),
                    ),
                );
            }

            $this->projectionStore->remove($projection->id());

            $this->logger?->info(
                sprintf('Projectionist: Projection "%s" removed.', $projection->id()->toString()),
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
                $this->logger?->debug(
                    sprintf('Projectionist: Projector for "%s" not found, skipped.', $projection->id()->toString()),
                );

                continue;
            }

            $projection->active();
            $this->projectionStore->save($projection);

            $this->logger?->info(sprintf(
                'Projectionist: Projector "%s" for "%s" is reactivated.',
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
            $projectionId = $projectorId->toProjectionId();

            if ($projections->has($projectionId)) {
                continue;
            }

            $projections = $projections->add(new Projection($projectionId));
        }

        return $projections;
    }

    private function handleMessage(int $index, Message $message, Projection $projection, bool $throwByError): void
    {
        $projector = $this->projector($projection->id());

        if (!$projector) {
            throw ProjectorNotFound::forProjectionId($projection->id());
        }

        $subscribeMethod = $this->projectorResolver->resolveSubscribeMethod($projector, $message);

        if (!$subscribeMethod) {
            $projection->changePosition($index);
            $this->projectionStore->save($projection);

            $this->logger?->debug(
                sprintf(
                    'Projectionist: Projector "%s" for "%s" has no subscribe method for "%s", continue.',
                    $projector::class,
                    $projection->id()->toString(),
                    $message->event()::class,
                ),
            );

            return;
        }

        try {
            $subscribeMethod($message);
        } catch (Throwable $e) {
            $this->logger?->error(
                sprintf(
                    'Projectionist: Projector "%s" for "%s" could not process the event "%s": %s',
                    $projector::class,
                    $projection->id()->toString(),
                    $message->event()::class,
                    $e->getMessage(),
                ),
            );

            $projection->error(ProjectionError::fromThrowable($e));
            $projection->incrementRetry();
            $this->projectionStore->save($projection);

            if ($throwByError) {
                throw new ProjectionistError(
                    $projector::class,
                    $projection->id(),
                    $e,
                );
            }

            return;
        }

        $projection->changePosition($index);
        $projection->resetRetry();
        $this->projectionStore->save($projection);

        $this->logger?->debug(
            sprintf(
                'Projectionist: Projector "%s" for "%s" processed the event "%s".',
                $projector::class,
                $projection->id()->toString(),
                $message->event()::class,
            ),
        );
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

            $this->logger?->info(
                sprintf(
                    'Projectionist: Projector for "%s" not found and has been marked as outdated.',
                    $projection->id()->toString(),
                ),
            );
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

            $this->logger?->info(
                sprintf(
                    'Projectionist: Retry projection "%s" (%d/%d) and set to active.',
                    $projection->id()->toString(),
                    $projection->retry(),
                    self::RETRY_LIMIT,
                ),
            );
        }
    }
}
