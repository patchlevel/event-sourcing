<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist;

use Closure;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projector\ProjectorMetadata;
use Patchlevel\EventSourcing\Metadata\Projector\ProjectorMetadataFactory;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use Patchlevel\EventSourcing\Projection\Projection\RunMode;
use Patchlevel\EventSourcing\Projection\Projection\Store\LockableProjectionStore;
use Patchlevel\EventSourcing\Projection\Projection\Store\ProjectionStore;
use Patchlevel\EventSourcing\Projection\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Projection\RetryStrategy\RetryStrategy;
use Patchlevel\EventSourcing\Store\Criteria;
use Patchlevel\EventSourcing\Store\Store;
use Psr\Log\LoggerInterface;
use Throwable;

use function array_map;
use function array_merge;
use function count;
use function in_array;
use function sprintf;

final class DefaultProjectionist implements Projectionist
{
    /** @var array<string, object>|null */
    private array|null $projectorIndex = null;

    /** @param iterable<object> $projectors */
    public function __construct(
        private readonly Store $streamableMessageStore,
        private readonly ProjectionStore $projectionStore,
        private readonly iterable $projectors,
        private readonly RetryStrategy $retryStrategy = new ClockBasedRetryStrategy(),
        private readonly ProjectorMetadataFactory $metadataFactory = new AttributeProjectorMetadataFactory(),
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function boot(
        ProjectionistCriteria|null $criteria = null,
        int|null $limit = null,
    ): void {
        $criteria ??= new ProjectionistCriteria();

        $this->logger?->info(
            'Projectionist: Start booting.',
        );

        $this->discoverNewProjections();
        $this->handleRetryProjections($criteria);
        $this->handleNewProjections($criteria);

        $this->findForUpdate(
            new ProjectionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [ProjectionStatus::Booting],
            ),
            function ($projections) use ($limit): void {
                $projections = $this->fastForwardFromNowProjections($projections);

                if (count($projections) === 0) {
                    $this->logger?->info('Projectionist: No projections in booting status, finish booting.');

                    return;
                }

                $startIndex = $this->lowestProjectionPosition($projections);

                $this->logger?->debug(
                    sprintf(
                        'Projectionist: Event stream is processed for booting from position %s.',
                        $startIndex,
                    ),
                );

                $stream = null;

                try {
                    $stream = $this->streamableMessageStore->load(
                        new Criteria(fromIndex: $startIndex),
                    );

                    $messageCounter = 0;

                    foreach ($stream as $message) {
                        $index = $stream->index();

                        if ($index === null) {
                            throw new UnexpectedError('Stream index is null, this should not happen.');
                        }

                        foreach ($projections as $projection) {
                            if (!$projection->isBooting()) {
                                continue;
                            }

                            if ($projection->position() >= $index) {
                                $this->logger?->debug(
                                    sprintf(
                                        'Projectionist: Projection "%s" is farther than the current position (%d > %d), continue booting.',
                                        $projection->id(),
                                        $projection->position(),
                                        $index,
                                    ),
                                );

                                continue;
                            }

                            $this->handleMessage($index, $message, $projection);
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

                foreach ($projections as $projection) {
                    if (!$projection->isBooting()) {
                        continue;
                    }

                    if ($projection->runMode() === RunMode::Once) {
                        $projection->finished();
                        $this->projectionStore->update($projection);

                        $this->logger?->info(sprintf(
                            'Projectionist: Projection "%s" run only once and has been set to finished.',
                            $projection->id(),
                        ));

                        continue;
                    }

                    $projection->active();
                    $this->projectionStore->update($projection);

                    $this->logger?->info(sprintf(
                        'Projectionist: Projection "%s" has been set to active after booting.',
                        $projection->id(),
                    ));
                }

                $this->logger?->info('Projectionist: Finish booting.');
            },
        );
    }

    public function run(
        ProjectionistCriteria|null $criteria = null,
        int|null $limit = null,
    ): void {
        $criteria ??= new ProjectionistCriteria();

        $this->logger?->info('Projectionist: Start processing.');

        $this->discoverNewProjections();
        $this->handleOutdatedProjections($criteria);
        $this->handleRetryProjections($criteria);

        $this->findForUpdate(
            new ProjectionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [ProjectionStatus::Active],
            ),
            function (array $projections) use ($limit): void {
                if (count($projections) === 0) {
                    $this->logger?->info('Projectionist: No projections to process, finish processing.');

                    return;
                }

                $startIndex = $this->lowestProjectionPosition($projections);

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

                        foreach ($projections as $projection) {
                            if (!$projection->isActive()) {
                                continue;
                            }

                            if ($projection->position() >= $index) {
                                $this->logger?->debug(
                                    sprintf(
                                        'Projectionist: Projection "%s" is farther than the current position (%d > %d), continue processing.',
                                        $projection->id(),
                                        $projection->position(),
                                        $index,
                                    ),
                                );

                                continue;
                            }

                            $this->handleMessage($index, $message, $projection);
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
            },
        );
    }

    public function teardown(ProjectionistCriteria|null $criteria = null): void
    {
        $criteria ??= new ProjectionistCriteria();

        $this->discoverNewProjections();

        $this->logger?->info('Projectionist: Start teardown outdated projections.');

        $this->findForUpdate(
            new ProjectionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [ProjectionStatus::Outdated],
            ),
            function (array $projections): void {
                foreach ($projections as $projection) {
                    $projector = $this->projector($projection->id());

                    if (!$projector) {
                        $this->logger?->warning(
                            sprintf(
                                'Projectionist: Projector for "%s" to teardown not found, skipped.',
                                $projection->id(),
                            ),
                        );

                        continue;
                    }

                    $teardownMethod = $this->resolveTeardownMethod($projector);

                    if (!$teardownMethod) {
                        $this->projectionStore->remove($projection);

                        $this->logger?->info(
                            sprintf(
                                'Projectionist: Projector "%s" for "%s" has no teardown method and was immediately removed.',
                                $projector::class,
                                $projection->id(),
                            ),
                        );

                        continue;
                    }

                    try {
                        $teardownMethod();

                        $this->logger?->debug(sprintf(
                            'Projectionist: For Projector "%s" for "%s" the teardown method has been executed and is now prepared to be removed.',
                            $projector::class,
                            $projection->id(),
                        ));
                    } catch (Throwable $e) {
                        $this->logger?->error(
                            sprintf(
                                'Projectionist: Projection "%s" for "%s" has an error in the teardown method, skipped: %s',
                                $projector::class,
                                $projection->id(),
                                $e->getMessage(),
                            ),
                        );
                        continue;
                    }

                    $this->projectionStore->remove($projection);

                    $this->logger?->info(
                        sprintf(
                            'Projectionist: Projection "%s" removed.',
                            $projection->id(),
                        ),
                    );
                }

                $this->logger?->info('Projectionist: Finish teardown.');
            },
        );
    }

    public function remove(ProjectionistCriteria|null $criteria = null): void
    {
        $criteria ??= new ProjectionistCriteria();

        $this->discoverNewProjections();

        $this->findForUpdate(
            new ProjectionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
            ),
            function (array $projections): void {
                foreach ($projections as $projection) {
                    $projector = $this->projector($projection->id());

                    if (!$projector) {
                        $this->projectionStore->remove($projection);

                        $this->logger?->info(
                            sprintf(
                                'Projectionist: Projection "%s" removed without a suitable projector.',
                                $projection->id(),
                            ),
                        );

                        continue;
                    }

                    $teardownMethod = $this->resolveTeardownMethod($projector);

                    if (!$teardownMethod) {
                        $this->projectionStore->remove($projection);

                        $this->logger?->info(
                            sprintf('Projectionist: Projection "%s" removed.', $projection->id()),
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

                    $this->projectionStore->remove($projection);

                    $this->logger?->info(
                        sprintf('Projectionist: Projection "%s" removed.', $projection->id()),
                    );
                }
            },
        );
    }

    public function reactivate(ProjectionistCriteria|null $criteria = null): void
    {
        $criteria ??= new ProjectionistCriteria();

        $this->discoverNewProjections();

        $this->findForUpdate(
            new ProjectionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [ProjectionStatus::Error, ProjectionStatus::Outdated, ProjectionStatus::Finished],
            ),
            function (array $projections): void {
                /** @var Projection $projection */
                foreach ($projections as $projection) {
                    $projector = $this->projector($projection->id());

                    if (!$projector) {
                        $this->logger?->debug(
                            sprintf('Projectionist: Projector for "%s" not found, skipped.', $projection->id()),
                        );

                        continue;
                    }

                    $error = $projection->projectionError();

                    if ($error) {
                        $projection->doRetry();
                        $projection->resetRetry();

                        $this->projectionStore->update($projection);

                        $this->logger?->info(sprintf(
                            'Projectionist: Projector "%s" for "%s" is reactivated.',
                            $projector::class,
                            $projection->id(),
                        ));

                        continue;
                    }

                    $projection->active();
                    $this->projectionStore->update($projection);

                    $this->logger?->info(sprintf(
                        'Projectionist: Projector "%s" for "%s" is reactivated.',
                        $projector::class,
                        $projection->id(),
                    ));
                }
            },
        );
    }

    /** @return list<Projection> */
    public function projections(ProjectionistCriteria|null $criteria = null): array
    {
        $criteria ??= new ProjectionistCriteria();

        $this->discoverNewProjections();

        return $this->projectionStore->find(
            new ProjectionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
            ),
        );
    }

    private function handleMessage(int $index, Message $message, Projection $projection): void
    {
        $projector = $this->projector($projection->id());

        if (!$projector) {
            throw ProjectorNotFound::forProjectionId($projection->id());
        }

        $subscribeMethods = $this->resolveSubscribeMethods($projector, $message);

        if ($subscribeMethods === []) {
            $projection->changePosition($index);
            $this->projectionStore->update($projection);

            $this->logger?->debug(
                sprintf(
                    'Projectionist: Projector "%s" for "%s" has no subscribe methods for "%s", continue.',
                    $projector::class,
                    $projection->id(),
                    $message->event()::class,
                ),
            );

            return;
        }

        try {
            foreach ($subscribeMethods as $subscribeMethod) {
                $subscribeMethod($message);
            }
        } catch (Throwable $e) {
            $this->logger?->error(
                sprintf(
                    'Projectionist: Projector "%s" for "%s" could not process the event "%s": %s',
                    $projector::class,
                    $projection->id(),
                    $message->event()::class,
                    $e->getMessage(),
                ),
            );

            $this->handleError($projection, $e);

            return;
        }

        $projection->changePosition($index);
        $projection->resetRetry();
        $this->projectionStore->update($projection);

        $this->logger?->debug(
            sprintf(
                'Projectionist: Projector "%s" for "%s" processed the event "%s".',
                $projector::class,
                $projection->id(),
                $message->event()::class,
            ),
        );
    }

    private function projector(string $projectorId): object|null
    {
        if ($this->projectorIndex === null) {
            $this->projectorIndex = [];

            foreach ($this->projectors as $projector) {
                $projectorId = $this->projectorMetadata($projector)->id;

                $this->projectorIndex[$projectorId] = $projector;
            }
        }

        return $this->projectorIndex[$projectorId] ?? null;
    }

    private function handleOutdatedProjections(ProjectionistCriteria $criteria): void
    {
        $this->findForUpdate(
            new ProjectionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [ProjectionStatus::Active, ProjectionStatus::Finished],
            ),
            function (array $projections): void {
                foreach ($projections as $projection) {
                    $projector = $this->projector($projection->id());

                    if ($projector) {
                        continue;
                    }

                    $projection->outdated();
                    $this->projectionStore->update($projection);

                    $this->logger?->info(
                        sprintf(
                            'Projectionist: Projector for "%s" not found and has been marked as outdated.',
                            $projection->id(),
                        ),
                    );
                }
            },
        );
    }

    private function handleRetryProjections(ProjectionistCriteria $criteria): void
    {
        $this->findForUpdate(
            new ProjectionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [ProjectionStatus::Error],
            ),
            function (array $projections): void {
                /** @var Projection $projection */
                foreach ($projections as $projection) {
                    $error = $projection->projectionError();

                    if ($error === null) {
                        continue;
                    }

                    $retryable = in_array(
                        $error->previousStatus,
                        [ProjectionStatus::New, ProjectionStatus::Booting, ProjectionStatus::Active],
                        true,
                    );

                    if (!$retryable) {
                        continue;
                    }

                    if (!$this->retryStrategy->shouldRetry($projection)) {
                        continue;
                    }

                    $projection->doRetry();
                    $this->projectionStore->update($projection);

                    $this->logger?->info(
                        sprintf(
                            'Projectionist: Retry projection "%s" (%d) and set back to %s.',
                            $projection->id(),
                            $projection->retryAttempt(),
                            $projection->status()->value,
                        ),
                    );
                }
            },
        );
    }

    /**
     * @param list<Projection> $projections
     *
     * @return list<Projection>
     */
    private function fastForwardFromNowProjections(array $projections): array
    {
        $latestIndex = null;
        $forwardedProjections = [];

        foreach ($projections as $projection) {
            $projector = $this->projector($projection->id());

            if (!$projector) {
                $forwardedProjections[] = $projection;

                continue;
            }

            if ($projection->runMode() === RunMode::FromBeginning || $projection->runMode() === RunMode::Once) {
                $forwardedProjections[] = $projection;

                continue;
            }

            if ($latestIndex === null) {
                $latestIndex = $this->latestIndex();
            }

            $projection->changePosition($latestIndex);
            $projection->active();
            $this->projectionStore->update($projection);

            $this->logger?->info(
                sprintf(
                    'Projectionist: Projector "%s" for "%s" is in "from now" mode: skip past messages and set to active.',
                    $projector::class,
                    $projection->id(),
                ),
            );
        }

        return $forwardedProjections;
    }

    private function handleNewProjections(ProjectionistCriteria $criteria): void
    {
        $this->findForUpdate(
            new ProjectionCriteria(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [ProjectionStatus::New],
            ),
            function (array $projections): void {
                foreach ($projections as $projection) {
                    $projector = $this->projector($projection->id());

                    if (!$projector) {
                        throw ProjectorNotFound::forProjectionId($projection->id());
                    }

                    $setupMethod = $this->resolveSetupMethod($projector);

                    if (!$setupMethod) {
                        $projection->booting();
                        $this->projectionStore->update($projection);

                        $this->logger?->debug(sprintf(
                            'Projectionist: Projector "%s" for "%s" has no setup method, continue.',
                            $projector::class,
                            $projection->id(),
                        ));

                        continue;
                    }

                    try {
                        $setupMethod();

                        $projection->booting();
                        $this->projectionStore->update($projection);

                        $this->logger?->debug(sprintf(
                            'Projectionist: For Projector "%s" for "%s" the setup method has been executed and is now prepared for data.',
                            $projector::class,
                            $projection->id(),
                        ));
                    } catch (Throwable $e) {
                        $this->logger?->error(sprintf(
                            'Projectionist: Projector "%s" for "%s" has an error in the setup method: %s',
                            $projector::class,
                            $projection->id(),
                            $e->getMessage(),
                        ));

                        $this->handleError($projection, $e);
                    }
                }
            },
        );
    }

    private function discoverNewProjections(): void
    {
        $this->findForUpdate(
            new ProjectionCriteria(),
            function (array $projections): void {
                foreach ($this->projectors as $projector) {
                    $metadata = $this->projectorMetadata($projector);

                    foreach ($projections as $projection) {
                        if ($projection->id() === $metadata->id) {
                            continue 2;
                        }
                    }

                    $this->projectionStore->add(new Projection(
                        $metadata->id,
                        $metadata->group,
                        $metadata->runMode,
                    ));

                    $this->logger?->info(
                        sprintf(
                            'Projectionist: New Projector "%s" was found and added to the projection store.',
                            $metadata->id,
                        ),
                    );
                }
            },
        );
    }

    private function resolveSetupMethod(object $projector): Closure|null
    {
        $metadata = $this->metadataFactory->metadata($projector::class);
        $method = $metadata->setupMethod;

        if ($method === null) {
            return null;
        }

        return $projector->$method(...);
    }

    private function resolveTeardownMethod(object $projector): Closure|null
    {
        $metadata = $this->metadataFactory->metadata($projector::class);
        $method = $metadata->teardownMethod;

        if ($method === null) {
            return null;
        }

        return $projector->$method(...);
    }

    /** @return iterable<Closure> */
    private function resolveSubscribeMethods(object $projector, Message $message): iterable
    {
        $event = $message->event();
        $metadata = $this->metadataFactory->metadata($projector::class);

        $methods = array_merge(
            $metadata->subscribeMethods[$event::class] ?? [],
            $metadata->subscribeMethods[Subscribe::ALL] ?? [],
        );

        return array_map(
            static fn (string $method) => $projector->$method(...),
            $methods,
        );
    }

    private function projectorMetadata(object $projector): ProjectorMetadata
    {
        return $this->metadataFactory->metadata($projector::class);
    }

    private function latestIndex(): int
    {
        $stream = $this->streamableMessageStore->load(null, 1, null, true);

        return $stream->index() ?: 1;
    }

    /** @param list<Projection> $projections */
    private function lowestProjectionPosition(array $projections): int
    {
        $min = null;

        foreach ($projections as $projection) {
            if ($min !== null && $projection->position() >= $min) {
                continue;
            }

            $min = $projection->position();
        }

        if ($min === null) {
            return 0;
        }

        return $min;
    }

    /** @param Closure(list<Projection>):void $closure */
    private function findForUpdate(ProjectionCriteria $criteria, Closure $closure): void
    {
        if (!$this->projectionStore instanceof LockableProjectionStore) {
            $closure($this->projectionStore->find($criteria));

            return;
        }

        $this->projectionStore->inLock(function () use ($closure, $criteria): void {
            $projections = $this->projectionStore->find($criteria);

            $closure($projections);
        });
    }

    private function handleError(Projection $projection, Throwable $throwable): void
    {
        $projection->error($throwable);
        $this->projectionStore->update($projection);
    }
}
