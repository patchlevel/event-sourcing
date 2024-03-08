<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Projection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\BalanceAdded;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\BankAccountCreated;

use function assert;

#[Subscriber('dummy-1')]
final class BankAccountProjector
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Setup]
    public function create(): void
    {
        $table = new Table('projection_bank_account');
        $table->addColumn('id', 'string')->setLength(36);
        $table->addColumn('name', 'string')->setLength(255);
        $table->addColumn('balance_in_cents', 'integer');
        $table->setPrimaryKey(['id']);

        $this->connection->createSchemaManager()->createTable($table);
    }

    #[Teardown]
    public function drop(): void
    {
        $this->connection->createSchemaManager()->dropTable('projection_bank_account');
    }

    #[Subscribe(BankAccountCreated::class)]
    public function handleBankAccountCreated(Message $message): void
    {
        $event = $message->event();

        assert($event instanceof BankAccountCreated);

        $this->connection->executeStatement(
            'INSERT INTO projection_bank_account (id, name, balance_in_cents) VALUES(:id, :name, 0);',
            [
                'id' => $event->accountId->toString(),
                'name' => $event->name,
            ],
        );
    }

    #[Subscribe(BalanceAdded::class)]
    public function handleBalanceAdded(Message $message): void
    {
        $event = $message->event();

        assert($event instanceof BalanceAdded);

        $this->connection->executeStatement(
            'UPDATE projection_bank_account SET balance_in_cents = balance_in_cents + :balance WHERE id = :id;',
            [
                'id' => $event->accountId->toString(),
                'balance' => $event->balanceInCents,
            ],
        );
    }
}
