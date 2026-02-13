# Sensitive Data

According to GDPR, personal data must be able to be deleted upon request.
But here we have the problem that our events are immutable and we cannot easily manipulate the event store.

The first solution is not to save the personal data in the Event Store at all
and use something different for this, for example a separate table or an ORM.

The other option the library offers is crypto shredding.
In this process, the personal data is encrypted with a key that is assigned to a subject (like person).
When saving and reading the events, this key is then used to convert the data.
This key with the subject is saved in a database.

As soon as a request for data deletion comes,
you can simply delete the key and the personal data can no longer be decrypted.

## Configuration

Encrypting and decrypting is handled by the library.
You just have to configure the events accordingly.
And if you use snapshots, you have to configure your aggregates too.

### DataSubjectId

In order for the correct key to be used, a subject ID must be defined.
Without Subject Id, no personal data can be encrypted or decrypted.

```php
use Patchlevel\EventSourcing\Identifier\Uuid;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;

final class EmailChanged
{
    public function __construct(
        #[DataSubjectId]
        public readonly Uuid $profileId,
        // ...
    ) {
    }
}
```

:::tip
You can use the `DataSubjectId` in aggregates for snapshots too.
:::    
### SensitiveData

Next, you have to mark the properties that should be encrypted with the `#[SensitiveData]` attribute.

```php
use Patchlevel\EventSourcing\Identifier\Uuid;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

final class EmailChanged
{
    public function __construct(
        #[DataSubjectId]
        public readonly Uuid $profileId,
        #[SensitiveData]
        public readonly string|null $email,
    ) {
    }
}
```

:::tip
You can use the `SensitiveData` in aggregates for snapshots too.
:::

If the information could not be decrypted, then a fallback value will be used.
The default fallback value is `null`.
You can change this by setting the `fallback` parameter or using the `fallbackCallable` parameter.

```php
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

final class ProfileChanged
{
    public function __construct(
        #[DataSubjectId]
        public readonly Uuid $profileId,
        #[SensitiveData(fallback: 'unknown')]
        public readonly string $name,
        #[SensitiveData(fallbackCallable: [self::class, 'createAnonymousEmail'])]
        public readonly string $email,
    ) {
    }

    public static function createAnonymousEmail(string $subjectId): string
    {
        return sprintf('%s@example.com', $subjectId);
    }
}
```

:::danger
You have to deal with this case in your business logic such as aggregates and subscriptions.
:::

:::note
The normalized data is encrypted. This means that this happens after the `extract` or before the `hydrate`.
:::

## Setup

In order for the system to work, a few things have to be done.

### Cipher Key Store

The keys must be stored somewhere. For this we provide a doctrine implementation.

```php
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Cryptography\DoctrineCipherKeyStore;

/** @var Connection $dbalConnection */
$cipherKeyStore = new DoctrineCipherKeyStore($dbalConnection);
```
To use the `DoctrineCipherKeyStore` you need to register this service in Doctrine Schema Director.
Then the table will be added automatically.

```php
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Cryptography\DoctrineCipherKeyStore;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Store\Store;

/**
 * @var Connection $dbalConnection
 * @var DoctrineCipherKeyStore $cipherKeyStore
 * @var Store $store
 */
$schemaDirector = new DoctrineSchemaDirector(
    $dbalConnection,
    new ChainDoctrineSchemaConfigurator([
        $store,
        $cipherKeyStore,
    ]),
);
```
### Hydrator

Now we put the whole thing together. The cryptographer encrypts and decrypts the data,
and is registered on a hydrator via the cryptography extension.

```php
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Extension\Cryptography\BaseCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyExtension;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\StackHydratorBuilder;

/** @var CipherKeyStore $cipherKeyStore */
$cryptographer = BaseCryptographer::createWithOpenssl($cipherKeyStore);

$hydrator = (new StackHydratorBuilder())
    ->useExtension(new CoreExtension())
    ->useExtension(new CryptographyExtension($cryptographer))
    ->build();
```

:::tip
You can specify the cipher method with the second parameter of `createWithOpenssl`.
:::

### Event Serializer Integration

The last step is to integrate the hydrator into the event store.

```php
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\Hydrator\Hydrator;

/** @var Hydrator $hydrator */
DefaultEventSerializer::createFromPaths(
    [__DIR__ . '/Events'],
    $hydrator,
);
```

:::note
More information can be found in the [events](events.md) documentation.
:::

### Snapshot Store Integration

And for the snapshot store.

```php
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\Hydrator\Hydrator;

/** @var Hydrator $hydrator */
$snapshotStore = DefaultSnapshotStore::createDefault(
    [
        /* adapters... */
    ],
    $hydrator,
);
```

:::note
More information can be found in the [snapshots](snapshots.md) documentation.
:::

:::success
Now you can save and read events with personal data.
:::

## Remove personal data

To remove personal data, you can either remove the key manually or do it with a processor.

```php
use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\Hydrator\Extension\Cryptography\Store\CipherKeyStore;

#[Processor('delete_personal_data')]
final class DeleteSensitiveDataProcessor
{
    public function __construct(
        private readonly CipherKeyStore $cipherKeyStore,
    ) {
    }

    #[Subscribe(UserHasRequestedDeletion::class)]
    public function handleUserHasRequestedDeletion(Message $message): void
    {
        $event = $message->event();

        $this->cipherKeyStore->removeWithSubjectId($event->personId);
    }
}
```
## Learn more

* [How to use the hydrator](https://github.com/patchlevel/hydrator)
* [How to define aggregates](aggregate.md)
* [How to define events](events.md)
* [How to normalize data](normalizer.md)
