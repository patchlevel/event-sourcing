<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorState;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStore;
use Patchlevel\EventSourcing\Store\StreamableStore;

use function array_map;
use function iterator_to_array;

final class DefaultProjectionist implements Projectionist
{
    /**
     * @param iterable<Projector> $projectors
     */
    public function __construct(
        private readonly StreamableStore $streamableMessageStore,
        private readonly ProjectorStore $projectorStore,
        private readonly iterable $projectors,
        private readonly ProjectorResolver $resolver = new MetadataProjectorResolver()
    ) {
    }

    public function boot(): void
    {
        $informationCollection = $this->information()->filterByProjectorStatus(ProjectorStatus::Booting);

        foreach ($informationCollection as $information) {
            if (!$information->projector) {
                continue; // throw an exception
            }

            $createMethod = $this->resolver->resolveCreateMethod($information->projector);
            $information->projectorState->active();

            if (!$createMethod) {
                continue;
            }

            $createMethod();
        }

        $stream = $this->streamableMessageStore->stream();

        foreach ($stream as $message) {
            foreach ($informationCollection as $information) {
                if (!$information->projector) {
                    continue; // throw an exception
                }

                $handleMethod = $this->resolver->resolveHandleMethod($information->projector, $message);
                $information->projectorState->incrementPosition();

                if (!$handleMethod) {
                    continue;
                }

                $handleMethod($message);
            }
        }

        $this->projectorStore->saveProjectorState(
            ...array_map(
                static fn (ProjectorInformation $information) => $information->projectorState,
                iterator_to_array($informationCollection)
            )
        );
    }

    public function run(?int $limit = null): void
    {
        $informationCollection = $this->information()->filterByProjectorStatus(ProjectorStatus::Active);
        $position = $informationCollection->minProjectorPosition();
        $stream = $this->streamableMessageStore->stream($position);

        foreach ($stream as $message) {
            $toSave = [];

            foreach ($informationCollection as $information) {
                if ($information->projectorState->position() > $position) {
                    continue;
                }

                $toSave[] = $information->projectorState;

                $handleMethod = $this->resolver->resolveHandleMethod($information->projector, $message);
                $handleMethod($message);

                $information->projectorState->incrementPosition();
            }

            $this->projectorStore->saveProjectorState(...$toSave);
            $position++;
        }
    }

    public function teardown(): void
    {
        $informationCollection = $this->information()->filterByProjectorStatus(ProjectorStatus::Outdated);

        foreach ($informationCollection as $information) {
            if (!$information->projector) {
                continue; // hmm........................
            }

            $dropMethod = $this->resolver->resolveDropMethod($information->projector);

            if (!$dropMethod) {
                continue;
            }

            $dropMethod();

            $this->projectorStore->removeProjectorState($information->projectorState->id());
        }
    }

    public function destroy(): void
    {
        $informationCollection = $this->information();

        foreach ($informationCollection as $information) {
            if ($information->projector) {
                $dropMethod = $this->resolver->resolveDropMethod($information->projector);

                if (!$dropMethod) {
                    continue;
                }

                $dropMethod();
            }

            $this->projectorStore->removeProjectorState($information->projectorState->id());
        }
    }

    private function information(): ProjectorInformationCollection
    {
        $informationCollection = new ProjectorInformationCollection();
        $projectorsStates = $this->projectorStore->getStateFromAllProjectors();

        foreach ($projectorsStates as $projectorState) {
            $informationCollection = $informationCollection->add(
                new ProjectorInformation(
                    $projectorState,
                    $this->findProjector($projectorState->id()),
                )
            );
        }

        foreach ($this->projectors as $projector) {
            if ($informationCollection->has($projector->projectorId())) {
                continue;
            }

            $informationCollection = $informationCollection->add(
                new ProjectorInformation(
                    new ProjectorState($projector->projectorId()),
                    $projector
                )
            );
        }

        return $informationCollection;
    }

    private function findProjector(ProjectorId $id): ?object
    {
        foreach ($this->projectors as $projector) {
            if ($id->toString() === $projector->projectorId()->toString()) {
                return $projector;
            }
        }

        return null;
    }
}
