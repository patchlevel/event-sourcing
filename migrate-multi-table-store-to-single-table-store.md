# Migrating from MultiTableStore to SingleTableStore

## Why should I migrate?

We removed the `MultiTableStore` without any replacement in version 3. To upgrade you need to migrate.

## Rationale

We removed the `MultiTableStore` because it does not bring any benefit for the user besides some more confidence due to 
be a little more similar to the "standard" ORM based databases.

This was done in this 
[PR](https://github.com/patchlevel/event-sourcing/pull/373), you can also find some more information in 
[this comment on GitHub](https://github.com/patchlevel/event-sourcing-bundle/issues/158#issuecomment-1888037198)

* data was duplicated, due to the meta table where all events need to be tracked with all filterable field for projections
* all fields for which you would want to filter would be needed in the meta table (and also in the aggregate table)
* this would lead in the long run that the meta table would hold mostly all the data
* write performance is greatly decreased due to writing in 2 different tables everytime

## Misc

For more convenience and kinda replacement for the overview we will provide 
[a bundle](https://github.com/patchlevel/event-sourcing-admin-bundle) which will visualize all aspects of the event 
sourced application.

## Migration path

### Notice

This migration would need a downtime depending how big your eventstore already is. But you can test this all locally 
beforehand.

### Setup

Please install the newest version of `patchlevel/event-sourcing:^2.x` first, this will ensure that everything is working 
as expected.

#### Create the database table

First we need to create the new eventstore table. For that we can change the configuration for store to the 
`SingleTableStore` and create a migration with `bin/console event-sourcing:migrations:diff` then. Remove from the 
migration all SQL statements which would drop tables or indexes for now.

If you are using the 
[bundle](https://github.com/patchlevel/event-sourcing-bundle) you can change the yaml configuration as follows to change 
the configuration:
```diff
patchlevel_event_sourcing:
    store:
-       type: multi_table
+       type: single_table
```

Also you would need to update the table name then since the bundle changed the default name of the meta table to 
`eventstore` instead of `eventstore_metadata`.

```diff
patchlevel_event_sourcing:
    store:
-       type: multi_table
+       type: single_table
+       options:
+           table_name: new_eventstore
```

The migration file should look like this here:

```php
<?php

declare(strict_types=1);

namespace EventSourcingMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240420145037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create SingleTableStore table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_store (id BIGSERIAL NOT NULL, aggregate VARCHAR(255) NOT NULL, aggregate_id VARCHAR(255) NOT NULL, playhead INT NOT NULL, event VARCHAR(255) NOT NULL, payload JSON NOT NULL, recorded_on TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, new_stream_start BOOLEAN DEFAULT false NOT NULL, archived BOOLEAN DEFAULT false NOT NULL, custom_headers JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BE4CE95BB77949FFD0BBCCBE34B91FA9 ON event_store (aggregate, aggregate_id, playhead)');
        $this->addSql('CREATE INDEX IDX_BE4CE95BB77949FFD0BBCCBE34B91FA961B169FE ON event_store (aggregate, aggregate_id, playhead, archived)');
        $this->addSql('COMMENT ON COLUMN event_store.recorded_on IS \'(DC2Type:datetimetz_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this should not be needed
    }
}
```

You could also just use this migration file here to be sure. You would still need to adjust the configuration to use the
`SingleTableStore` though and also sync the table name you chose. In our example here it is `event_store`.

#### Migrating

Now we create a command to execute the migration. For that, we are using the `Pipeline` feature. So we need now the new 
`SingleTableStore` and also the old `MultiTableStore` and configure the `Pipeline` properly. The old `MultiTableStore` 
will be the `SourceStore` for the `Pipeline` and the new `SingleTableStore` will be the `TargetStore`. We can also use 
this occasion to clean up the eventstream and remove some old events which are not used anymore. For that you could add 
some `Middlewares` to the `Pipeline` but you don't need to do so.

Here we will show an example using a command made with `symfony/console` and using dependency injection for the 
`SingleTableStore` (since we already updated the configuration before), `EventSerializer` and `AggregateRootRegistry`.

```php
<?php declare(strict_types=1);

namespace App\Infrastructure\Command;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:migrate-to-single-table-store',
    description: 'Migrate structure from multi table to single table store.'
)]
class MigrateToSingleTableStoreCommand extends Command
{
    public function __construct(
        private readonly Store $newSingleTableStore,
        private readonly Connection $eventstoreConnection,
        private readonly EventSerializer $serializer,
        private readonly AggregateRootRegistry $aggregateRootRegistry,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $oldMultiTableStore = new MultiTableStore(
            $this->eventstoreConnection, // connection to the old table, this must not be the same as the new one
            $this->serializer,
            $this->aggregateRootRegistry,
            // 'eventstore' // here comes the name of the metadata table
        );

        $pipeline = new Pipeline(
            new StoreSource($oldMultiTableStore),
            new StoreTarget($this->newSingleTableStore),
            [
                new ExcludeEventMiddleware([
                    /* add Events here that are maybe not needed anymore */
                ]),
                new RecalculatePlayheadMiddleware(), // this is only needed if you are dropping events, but you can also just keep it regardless
            ]
        );
        $pipeline->run();

        return Command::SUCCESS;
    }
}
```

We can now execute this command which will then read all passed evens from the old store and push them into the new one.

#### Cleanup

After the command successfully ran, you can now use the application like you did before and if everything looks fine
remove the old tables. For that, you can create again a migration file with `bin/console event-sourcing:migrations:diff` 
and now keep all the SQL statements we removed before. These statements should now only consist of dropping the tables 
from the `MultiTableStore` and indexes.
