<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorState;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStateCollection;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStore;
use Patchlevel\EventSourcing\Store\StreamableStore;

use function array_values;
use function iterator_to_array;

final class DefaultProjectionist implements Projectionist
{
    /** @var array<string, Projector>|null */
    private ?array $projectorHashmap = null;

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
        $projectorStates = $this->projectorStates()->filterByProjectorStatus(ProjectorStatus::Booting);

        foreach ($projectorStates as $projectorState) {
            $projector = $this->findProjector($projectorState->id());

            if (!$projector) {
                continue; // throw an exception
            }

            $createMethod = $this->resolver->resolveCreateMethod($projector);
            $projectorState->active();

            if (!$createMethod) {
                continue;
            }

            $createMethod();
        }

        $stream = $this->streamableMessageStore->stream();

        foreach ($stream as $message) {
            foreach ($projectorStates as $projectorState) {
                $projector = $this->findProjector($projectorState->id());

                if (!$projector) {
                    continue; // throw an exception
                }

                $handleMethod = $this->resolver->resolveHandleMethod($projector, $message);
                $projectorState->incrementPosition();

                if (!$handleMethod) {
                    continue;
                }

                $handleMethod($message);
            }
        }

        $this->projectorStore->saveProjectorState(...iterator_to_array($projectorStates));
    }

    public function run(?int $limit = null): void
    {
        $projectorStates = $this->projectorStates()->filterByProjectorStatus(ProjectorStatus::Active);
        $position = $projectorStates->minProjectorPosition();
        $stream = $this->streamableMessageStore->stream($position);

        foreach ($stream as $message) {
            $toSave = [];

            foreach ($projectorStates as $projectorState) {
                if ($projectorState->position() > $position) {
                    continue;
                }

                $projector = $this->findProjector($projectorState->id());

                if (!$projector) {
                    continue;
                }

                $toSave[] = $projectorState;

                $handleMethod = $this->resolver->resolveHandleMethod($projector, $message);

                if ($handleMethod) {
                    $handleMethod($message);
                }

                $projectorState->incrementPosition();
            }

            $this->projectorStore->saveProjectorState(...$toSave);
            $position++;
        }
    }

    public function teardown(): void
    {
        $projectorStates = $this->projectorStates()->filterByProjectorStatus(ProjectorStatus::Outdated);

        foreach ($projectorStates as $projectorState) {
            $projector = $this->findProjector($projectorState->id());

            if (!$projector) {
                continue; // hmm........................
            }

            $dropMethod = $this->resolver->resolveDropMethod($projector);

            if (!$dropMethod) {
                continue;
            }

            $dropMethod();

            $this->projectorStore->removeProjectorState($projectorState->id());
        }
    }

    public function destroy(): void
    {
        $projectorStates = $this->projectorStates();

        foreach ($projectorStates as $projectorState) {
            $projector = $this->findProjector($projectorState->id());

            if ($projector) {
                $dropMethod = $this->resolver->resolveDropMethod($projector);

                if ($dropMethod) {
                    $dropMethod();
                }
            }

            $this->projectorStore->removeProjectorState($projectorState->id());
        }
    }

    private function projectorStates(): ProjectorStateCollection
    {
        $projectorsStates = $this->projectorStore->getStateFromAllProjectors();

        foreach ($this->projectors as $projector) {
            if ($projectorsStates->has($projector->projectorId())) {
                continue;
            }

            $projectorsStates = $projectorsStates->add(new ProjectorState($projector->projectorId()));
        }

        return $projectorsStates;
    }

    private function findProjector(ProjectorId $id): ?Projector
    {
        if ($this->projectorHashmap === null) {
            $this->projectorHashmap = [];

            foreach ($this->projectors as $projector) {
                $this->projectorHashmap[$projector->projectorId()->toString()] = $projector;
            }
        }

        return $this->projectorHashmap[$id->toString()] ?? null;
    }

    /**
     * @return list<ProjectorState>
     */
    public function status(): array
    {
        return array_values([...$this->projectorStates()]);
    }
}
