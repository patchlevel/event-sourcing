<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;

use function assert;

#[Projector('dummy_1')]
final class ProfileProjector
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Setup]
    public function create(): void
    {
        $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS projection_profile (id VARCHAR PRIMARY KEY, name VARCHAR);');
    }

    #[Teardown]
    public function drop(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS projection_profile;');
    }

    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(Message $message): void
    {
        $profileCreated = $message->event();

        assert($profileCreated instanceof ProfileCreated);

        $this->connection->executeStatement(
            'INSERT INTO projection_profile (`id`, `name`) VALUES(:id, :name);',
            [
                'id' => $profileCreated->profileId->toString(),
                'name' => $profileCreated->name,
            ],
        );
    }
}
