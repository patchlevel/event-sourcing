<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Container\Fixture;

use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\QueryBus\QueryBus;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Command\CreateProfile;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\ProfileId;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Query\QueryProfileName;

use function is_string;

/**
 * An application service, created by the application container,
 * that depends on services of the event sourcing container.
 */
final class ProfileRegistration
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function register(ProfileId $profileId, string $name): string
    {
        $this->commandBus->dispatch(new CreateProfile($profileId, $name));

        $name = $this->queryBus->dispatch(new QueryProfileName($profileId));

        return is_string($name) ? $name : '';
    }
}
