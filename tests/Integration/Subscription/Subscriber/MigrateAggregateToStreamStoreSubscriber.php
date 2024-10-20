<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Pipeline;
use Patchlevel\EventSourcing\Message\Translator\AggregateToStreamHeaderTranslator;
use Patchlevel\EventSourcing\Pipeline\Middleware\Middleware;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchableSubscriber;
use function count;

#[Subscriber('migrate', RunMode::Once)]
final class MigrateAggregateToStreamStoreSubscriber implements BatchableSubscriber
{
    private readonly SchemaDirector $schemaDirector;

    /** @var list<Message> */
    private array $messages = [];

    /**
     * @var list<Middleware>
     */
    private readonly array $middlewares;

    public function __construct(
        private readonly StreamDoctrineDbalStore $targetStore,
    ) {
        $this->schemaDirector = new DoctrineSchemaDirector(
            $targetStore->connection(),
            new ChainDoctrineSchemaConfigurator([$targetStore]),
        );

        $this->middlewares = [
            new AggregateToStreamHeaderTranslator()
        ];
    }

    #[Subscribe('*')]
    public function handle(Message $message): void
    {
        $this->messages[] = $message;
    }

    #[Subscribe('*')]
    public function kafka(Message $message): void
    {
        $pipeline = new Pipeline([$message], $this->middlewares);

        foreach ($pipeline as $message) {
            $this->kafka->publish($message);
        }
    }

    public function beginBatch(): void
    {
        $this->messages = [];
    }

    public function commitBatch(): void
    {
        $pipeline = new Pipeline($this->messages, $this->middlewares);
        $this->messages = [];

        $this->targetStore->save(...$pipeline);
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
