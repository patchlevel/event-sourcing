<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Projection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\BalanceAdded;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\BankAccountCreated;

#[Projector('dummy', 1)]
final class BankAccountProjection
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Create]
    public function create(): void
    {
        $table = new Table('projection_bank_account');
        $table->addColumn('id', 'string');
        $table->addColumn('name', 'string');
        $table->addColumn('balance_in_cents', 'integer');
        $table->setPrimaryKey(['id']);

        $this->connection->createSchemaManager()->createTable($table);
    }

    #[Drop]
    public function drop(): void
    {
        $this->connection->createSchemaManager()->dropTable('projection_bank_account');
    }

    #[Subscribe(BankAccountCreated::class)]
    public function handleBankAccountCreated(Message $message): void
    {
        $event = $message->event();

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

        $this->connection->executeStatement(
            'UPDATE projection_bank_account SET balance_in_cents = balance_in_cents + :balance WHERE id = :id;',
            [
                'id' => $event->accountId->toString(),
                'balance' => $event->balanceInCents,
            ],
        );
    }
}
