<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Projection;

use Doctrine\DBAL\Driver\Connection;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;

final class ProfileProjection implements Projection
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getHandledMessages(): iterable
    {
        yield ProfileCreated::class => 'applyProfileCreated';
    }

    public function drop(): void
    {
        $this->connection->exec('DROP TABLE profile;');
    }
}
