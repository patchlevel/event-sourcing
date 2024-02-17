<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist;

use Closure;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projector\ProjectorMetadataFactory;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionError;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use Patchlevel\EventSourcing\Projection\Projection\Store\ProjectionStore;
use Patchlevel\EventSourcing\Store\Criteria;
use Patchlevel\EventSourcing\Store\Store;
use Psr\Log\LoggerInterface;
use Throwable;

use function array_map;
use function array_merge;
use function sprintf;

final class DefaultProjectionist implements Projectionist
{
    private const RETRY_LIMIT = 5;

    /** @var array<string, object>|null */
    private array|null $projectorIndex = null;

    /** @param iterable<object> $projectors */
    public function __construct(
        private readonly Store $streamableMessageStore,
        private readonly ProjectionStore $projectionStore,
        private readonly iterable $projectors,
        private readonly ProjectorMetadataFactory $metadataFactory = new AttributeProjectorMetadataFactory(),
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
                $projection->id(),
            ));

            $setupMethod = $this->resolveSetupMethod($projector);

            if (!$setupMethod) {
                $this->logger?->debug(sprintf(
                    'Projectionist: Projector "%s" for "%s" has no setup method, continue.',
                    $projector::class,
                    $projection->id(),
                ));

                continue;
            }

            try {
                $setupMethod();
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

        $this->handleFromNowProjections($projections);

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
                                $projection->id(),
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
                $projection->id(),
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
                                $projection->id(),
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
                        $projection->id(),
                    ),
                );

                continue;
            }

            $teardownMethod = $this->resolveTeardownMethod($projector);

            if (!$teardownMethod) {
                $this->projectionStore->remove($projection->id());

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

            $this->projectionStore->remove($projection->id());

            $this->logger?->info(
                sprintf(
                    'Projectionist: Projection "%s" removed.',
                    $projection->id(),
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
                    sprintf('Projectionist: Projection "%s" removed without a suitable projector.', $projection->id()),
                );

                continue;
            }

            $teardownMethod = $this->resolveTeardownMethod($projector);

            if (!$teardownMethod) {
                $this->projectionStore->remove($projection->id());

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

            $this->projectionStore->remove($projection->id());

            $this->logger?->info(
                sprintf('Projectionist: Projection "%s" removed.', $projection->id()),
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
                    sprintf('Projectionist: Projector for "%s" not found, skipped.', $projection->id()),
                );

                continue;
            }

            $projection->active();
            $this->projectionStore->save($projection);

            $this->logger?->info(sprintf(
                'Projectionist: Projector "%s" for "%s" is reactivated.',
                $projector::class,
                $projection->id(),
            ));
        }
    }

    public function projections(): ProjectionCollection
    {
        $projections = $this->projectionStore->all();

        foreach ($this->projectors as $projector) {
            $projectorId = $this->projectorId($projector);

            if ($projections->has($projectorId)) {
                continue;
            }

            $projections = $projections->add(new Projection($projectorId));
        }

        return $projections;
    }

    private function handleMessage(int $index, Message $message, Projection $projection, bool $throwByError): void
    {
        $projector = $this->projector($projection->id());

        if (!$projector) {
            throw ProjectorNotFound::forProjectionId($projection->id());
        }

        $subscribeMethods = $this->resolveSubscribeMethods($projector, $message);

        if ($subscribeMethods === []) {
            $projection->changePosition($index);
            $this->projectionStore->save($projection);

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
                $projectorId = $this->projectorId($projector);

                $this->projectorIndex[$projectorId] = $projector;
            }
        }

        return $this->projectorIndex[$projectorId] ?? null;
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
                    $projection->id(),
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
                    $projection->id(),
                    $projection->retry(),
                    self::RETRY_LIMIT,
                ),
            );
        }
    }

    private function handleFromNowProjections(ProjectionCollection $projections): void
    {
        $latestIndex = null;

        foreach ($projections->filterByProjectionStatus(ProjectionStatus::Booting) as $projection) {
            $projector = $this->projector($projection->id());

            if (!$projector) {
                continue;
            }

            $metadata = $this->metadataFactory->metadata($projector::class);

            if (!$metadata->fromNow) {
                continue;
            }

            if ($latestIndex === null) {
                $latestIndex = $this->latestIndex();
            }

            $projection->changePosition($latestIndex);
            $projection->active();
            $this->projectionStore->save($projection);

            $this->logger?->info(
                sprintf(
                    'Projectionist: Projector "%s" for "%s" is in "from now" mode: skip past messages and set to active.',
                    $projector::class,
                    $projection->id(),
                ),
            );
        }
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

    private function projectorId(object $projector): string
    {
        return $this->metadataFactory->metadata($projector::class)->id;
    }

    private function latestIndex(): int
    {
        $stream = $this->streamableMessageStore->load(null, 1, null, true);

        return $stream->index() ?: 1;
    }
}
