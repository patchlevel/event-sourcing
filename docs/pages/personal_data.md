# Personal Data (GDPR)

According to GDPR, personal data must be able to be deleted upon request.
But here we have the problem that our events are immutable and we cannot easily manipulate the event store.

The first solution is not to save the personal data in the Event Store at all
and use something different for this, for example a separate table or an ORM.

The other option the library offers is crypto shredding.
In this process, the personal data is encrypted with a key that is assigned to a subject (person).
When saving and reading the events, this key is then used to convert the data.
This key with the subject is saved in a database.

As soon as a request for data deletion comes,
you can simply delete the key and the personal data can no longer be decrypted.

## Configuration

Encrypting and decrypting is handled by the library.
You just have to configure the events accordingly.

### PersonalData

First of all, we have to mark the fields that contain personal data.

```php
use Patchlevel\EventSourcing\Attribute\PersonalData;

final class EmailChanged
{
    public function __construct(
        #[PersonalData]
        public readonly string|null $email,
    ) {
    }
}
```
If the information could not be decrypted, then a fallback value is inserted.
The default fallback value is `null`.
You can change this by setting the `fallback` parameter.
In this case `unknown` is added:

```php
use Patchlevel\EventSourcing\Attribute\PersonalData;

final class EmailChanged
{
    public function __construct(
        #[PersonalData(fallback: 'unknown')]
        public readonly string|null $email,
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
use Patchlevel\EventSourcing\Attribute\DataSubjectId;
use Patchlevel\EventSourcing\Attribute\PersonalData;

final class EmailChanged
{
    public function __construct(
        #[DataSubjectId]
        public readonly string $personId,
        #[PersonalData(fallback: 'unknown')]
        public readonly string|null $email,
    ) {
    }
}
```
!!! warning

    A subject ID can not be a personal data.
    
## Setup

In order for the system to work, a few things have to be done.

!!! tip

    You can use named constructor `DefaultEventPayloadCryptographer::createWithOpenssl` to skip some necessary setups.
    
### Cipher Key Factory

We need a factory to generate keys. We provide an openssl implementation by default.

```php
use Patchlevel\EventSourcing\Cryptography\Cipher\OpensslCipherKeyFactory;

$cipherKeyFactory = new OpensslCipherKeyFactory();
$cipherKey = $cipherKeyFactory();
```
You can change the algorithm by passing it as a parameter.

```php
use Patchlevel\EventSourcing\Cryptography\Cipher\OpensslCipherKeyFactory;

$cipherKeyFactory = new OpensslCipherKeyFactory('aes256');
$cipherKey = $cipherKeyFactory();
```
!!! tip

    With `OpensslCipherKeyFactory::supportedMethods()` you can get a list of all available algorithms.
    
### Cipher Key Store

The keys must be stored somewhere. For this we provide a doctrine implementation.

```php
use Patchlevel\EventSourcing\Cryptography\Store\DoctrineCipherKeyStore;

$cipherKeyStore = new DoctrineCipherKeyStore($dbalConnection);

$cipherKeyStore->store('personId', $cipherKey);
$cipherKey = $cipherKeyStore->get('personId');
$cipherKeyStore->remove('personId');
```
To use the `DoctrineCipherKeyStore` you need to register this service in Doctrine Schema Director.
Then the table will be added automatically.

```php
$schemaDirector = new DoctrineSchemaDirector(
    $dbalConnection,
    new ChainDoctrineSchemaConfigurator([
        $store,
        $cipherKeyStore,
    ]),
);
```
### Cipher

The encryption and decryption is handled by the `Cipher`.
We offer an openssl implementation by default.

```php
use Patchlevel\EventSourcing\Cryptography\Cipher\OpensslCipher;

$cipher = new OpensslCipher();

$encrypted = $cipher->encrypt($cipherKey, $value);
$value = $cipher->decrypt($cipherKey, $encrypted);
```
!!! note

    If the encryption or decryption fails, an exception `EncryptionFailed` or `DecryptionFailed` is thrown.
    
### Event Payload Cryptographer

Now we have to put the whole thing together in an Event Payload Cryptographer.

```php
use Patchlevel\EventSourcing\Cryptography\DefaultEventPayloadCryptographer;

$cryptographer = new DefaultEventPayloadCryptographer(
    $eventMetadataFactory,
    $cipherKeyStore,
    $cipherKeyFactory,
    $cipher,
);
```
You can also use the shortcut with openssl.

```php
use Patchlevel\EventSourcing\Cryptography\DefaultEventPayloadCryptographer;

$cryptographer = DefaultEventPayloadCryptographer::createWithOpenssl(
    $eventMetadataFactory,
    $cipherKeyStore,
);
```
### Integration

The last step is to integrate the cryptographer into the event store.

```php
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;

DefaultEventSerializer::createFromPaths(
    [__DIR__ . '/Events'],
    cryptographer: $cryptographer,
);
```
!!! success

    Now you can save and read events with personal data.
    
## Remove personal data

To remove personal data, you can either remove the key manually or do it with a processor.

```php
use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Cryptography\Store\CipherKeyStore;
use Patchlevel\EventSourcing\Message\Message;

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