<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\AggregateToStreamHeaderMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchableSubscriber;

use function count;

use const INF;

#[Subscriber('migrate', RunMode::Once)]
final class MigrateAggregateToStreamStoreSubscriber implements BatchableSubscriber
{
    private readonly SchemaDirector $schemaDirector;

    /** @var list<Message> */
    private array $messages = [];

    private readonly Pipeline $pipeline;

    public function __construct(
        private readonly StreamDoctrineDbalStore $targetStore,
    ) {
        $this->schemaDirector = new DoctrineSchemaDirector(
            $targetStore->connection(),
            new ChainDoctrineSchemaConfigurator([$targetStore]),
        );

        $this->pipeline = new Pipeline(
            new StoreTarget($this->targetStore),
            new AggregateToStreamHeaderMiddleware(),
            INF,
        );
    }

    #[Subscribe('*')]
    public function handle(Message $message): void
    {
        $this->messages[] = $message;
    }

    public function beginBatch(): void
    {
        $this->messages = [];
    }

    public function commitBatch(): void
    {
        $this->pipeline->run($this->messages);

        $this->messages = [];
    }

    public function rollbackBatch(): void
    {
        $this->messages = [];
    }

    public function forceCommit(): bool
    {
        return count($this->messages) >= 10_000;
    }

    #[Setup]
    public function setup(): void
    {
        $this->schemaDirector->create();
    }

    #[Teardown]
    public function teardown(): void
    {
        $this->schemaDirector->drop();
    }
}
