# Personal Data (GDPR)

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

### PersonalData

First of all, we have to mark the fields that contain personal data.
For our example, we use events, but you can do the same with aggregates.

```php
use Patchlevel\Hydrator\Attribute\PersonalData;

final class EmailChanged
{
    public function __construct(
        #[PersonalData]
        public readonly string|null $email,
    ) {
    }
}
```
!!! tip

    You can use the `PersonalData` in aggregates for snapshots too.
    
If the information could not be decrypted, then a fallback value is inserted.
The default fallback value is `null`.
You can change this by setting the `fallback` parameter.
In this case `unknown` is added:

```php
use Patchlevel\Hydrator\Attribute\PersonalData;

final class EmailChanged
{
    public function __construct(
        #[PersonalData(fallback: 'unknown')]
        public readonly string $email,
    ) {
    }
}
```
!!! danger

    You have to deal with this case in your business logic such as aggregates and subscriptions.
    
!!! warning

    You need to define a subject ID to use the personal data attribute.
    
!!! note

    The normalized data is encrypted. This means that this happens after the 'extract' or before the 'hydrate'.
    
### DataSubjectId

In order for the correct key to be used, a subject ID must be defined.
Without Subject Id, no personal data can be encrypted or decrypted.

```php
use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\PersonalData;

final class EmailChanged
{
    public function __construct(
        #[DataSubjectId]
        public readonly string $personId,
        #[PersonalData(fallback: 'unknown')]
        public readonly string $email,
    ) {
    }
}
```
!!! tip

    You can use the `DataSubjectId` in aggregates for snapshots too.
    
!!! warning

    A subject ID can not be a personal data.
    
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
### Personal Data Payload Cryptographer

Now we have to put the whole thing together in a Personal Data Payload Cryptographer.

```php
use Patchlevel\EventSourcing\Cryptography\Store\CipherKeyStore;
use Patchlevel\Hydrator\Cryptography\PersonalDataPayloadCryptographer;

/** @var CipherKeyStore $cipherKeyStore */
$cryptographer = PersonalDataPayloadCryptographer::createWithOpenssl($cipherKeyStore);
```
!!! tip

    You can specify the cipher method with the second parameter.
    
### Event Serializer Integration

The last step is to integrate the cryptographer into the event store.

```php
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\Hydrator\Cryptography\PersonalDataPayloadCryptographer;

/** @var PersonalDataPayloadCryptographer $cryptographer */
DefaultEventSerializer::createFromPaths(
    [__DIR__ . '/Events'],
    cryptographer: $cryptographer,
);
```
!!! note

    More information about the events can be found [here](./events.md).
    
### Snapshot Store Integration

And for the snapshot store.

```php
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\Hydrator\Cryptography\PersonalDataPayloadCryptographer;

/** @var PersonalDataPayloadCryptographer $cryptographer */
$snapshotStore = DefaultSnapshotStore::createDefault(
    [
        /* adapters... */
    ],
    $cryptographer,
);
```
!!! note

    More information about the snapshot store can be found [here](./snapshots.md).
    
!!! success

    Now you can save and read events with personal data.
    
## Remove personal data

To remove personal data, you can either remove the key manually or do it with a processor.

```php
use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\Hydrator\Cryptography\Store\CipherKeyStore;

#[Processor('delete_personal_data')]
final class DeletePersonalDataProcessor
{
    public function __construct(
        private readonly CipherKeyStore $cipherKeyStore,
    ) {
    }

    #[Subscribe(UserHasRequestedDeletion::class)]
    public function handleUserHasRequestedDeletion(Message $message): void
    {
        $event = $message->event();

        $this->cipherKeyStore->remove($event->personId);
    }
}
```
## Learn more

* [How to use the hydrator](https://github.com/patchlevel/hydrator)
* [How to define aggregates](aggregate.md)
* [How to define events](events.md)
* [How to normalize data](normalizer.md)
