<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Projection\Projector\Projector;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorId;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorData;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStore;
use Patchlevel\EventSourcing\Store\StreamableStore;

use function array_key_exists;
use function array_map;
use function iterator_to_array;

final class DefaultProjectionist implements Projectionist
{
    /**
     * @param iterable<Projector> $projectors
     */
    public function __construct(
        private readonly StreamableStore $streamableMessageStore,
        private readonly ProjectorStore $positionStore,
        private readonly iterable $projectors,
        private readonly ProjectorResolver $resolver = new MetadataProjectorResolver()
    ) {
    }

    public function boot(): void
    {
        $informationCollection = $this->information()->filterByProjectorStatus(ProjectorStatus::Pending);

        foreach ($informationCollection as $information) {
            if (!$information->projector) {
                continue;
            }

            $createMethod = $this->resolver->resolveCreateMethod($information->projector);
            $information->projectorData->running();

            if (!$createMethod) {
                continue;
            }

            $createMethod();
        }

        $stream = $this->streamableMessageStore->stream();

        foreach ($stream as $message) {
            foreach ($informationCollection as $information) {
                if (!$information->projector) {
                    continue;
                }

                $handleMethod = $this->resolver->resolveHandleMethod($information->projector, $message);
                $information->projectorData->incrementPosition();

                if (!$handleMethod) {
                    continue;
                }

                $handleMethod($message);
            }
        }

        $this->positionStore->save(
            ...array_map(
                static fn (ProjectorInformation $information) => $information->projectorData,
                iterator_to_array($informationCollection)
            )
        );
    }

    public function run(?int $limit = null): void
    {
        $informationCollection = $this->information()->filterByProjectorStatus(ProjectorStatus::Running);
        $position = $informationCollection->minProjectorPosition();
        $stream = $this->streamableMessageStore->stream($position);

        foreach ($stream as $message) {
            $toSave = [];

            foreach ($informationCollection as $information) {
                if ($information->projectorData->position() > $position) {
                    continue;
                }

                $toSave[] = $information->projectorData;

                $this->resolver->resolveHandleMethod($information->projector, $message)($message);
                $information->projectorData->incrementPosition();
            }

            $this->positionStore->save(...$toSave);
            $position++;
        }
    }

    private function information(): ProjectorInformationCollection
    {
        $informationCollection = new ProjectorInformationCollection();
        $found = [];

        $projectorDataList = $this->positionStore->all();

        foreach ($projectorDataList as $projectorData) {
            $informationCollection = $informationCollection->add(
                new ProjectorInformation(
                    $projectorData,
                    $this->findProjector($projectorData->id()),
                )
            );

            $found[$projectorData->id()->toString()] = true;
        }

        foreach ($this->projectors as $projector) {
            $projectorId = $projector->projectorId();

            if (array_key_exists($projectorId->toString(), $found)) {
                continue;
            }

            $informationCollection = $informationCollection->add(
                new ProjectorInformation(
                    new ProjectorData($projectorId),
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
