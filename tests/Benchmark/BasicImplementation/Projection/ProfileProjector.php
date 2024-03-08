<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberUtil;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;

use function assert;

#[Subscriber('profile')]
final class ProfileProjector
{
    use SubscriberUtil;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Setup]
    public function create(): void
    {
        $this->connection->executeStatement("CREATE TABLE IF NOT EXISTS {$this->table()} (id VARCHAR PRIMARY KEY, name VARCHAR);");
    }

    #[Teardown]
    public function drop(): void
    {
        $this->connection->executeStatement("DROP TABLE IF EXISTS {$this->table()};");
    }

    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(Message $message): void
    {
        $profileCreated = $message->event();

        assert($profileCreated instanceof ProfileCreated);

        $this->connection->insert(
            $this->table(),
            [
                'id' => $profileCreated->profileId->toString(),
                'name' => $profileCreated->name,
            ],
        );
    }

    #[Subscribe(NameChanged::class)]
    public function onNameChanged(Message $message): void
    {
        $nameChanged = $message->event();

        assert($nameChanged instanceof NameChanged);

        $this->connection->update(
            $this->table(),
            ['name' => $nameChanged->name],
            ['id' => $message->header(AggregateHeader::class)->aggregateId],
        );
    }

    public function table(): string
    {
        return 'projection_' . $this->subscriberId();
    }
}
