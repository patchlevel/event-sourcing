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
use Patchlevel\EventSourcing\Pipeline\Source\InMemorySource;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchableSubscriber;

#[Subscriber('migrate', RunMode::Once)]
final class MigrateAggregateToStreamStoreSubscriber implements BatchableSubscriber
{
    private const BATCH_SIZE = 10_000;

    private readonly SchemaDirector $schemaDirector;

    /**
     * @var list<Message>
     */
    private array $messages = [];

    public function __construct(
        private readonly StreamDoctrineDbalStore $targetStore,
    ) {
        $this->schemaDirector = new DoctrineSchemaDirector(
            $targetStore->connection(),
            new ChainDoctrineSchemaConfigurator([
                $targetStore,
            ]),
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
        $messages = $this->messages;
        $this->messages = [];

        Pipeline::execute(
            new InMemorySource($messages),
            new StoreTarget($this->targetStore),
            new AggregateToStreamHeaderMiddleware(),
            self::BATCH_SIZE * 10, // make sure we have only one batch
        );
    }

    public function rollbackBatch(): void
    {
        $this->messages = [];
    }

    public function forceCommit(): bool
    {
        return count($this->messages) >= self::BATCH_SIZE;
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
