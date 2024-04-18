# Aggregate ID

The `aggregate id` is a unique identifier for an aggregate.
It is used to identify the aggregate in the event store.
The `aggregate` does not care how the id is generated,
since only an aggregate-wide unique string is expected in the store.

This library provides you with a few options for generating the id.

!!! warning

    Performance reasons, the default configuration of the store require an uuid string for `aggregate id`.
    But technically, for the library, it can be any string.
    If you want to use a custom id, you have to change the `aggregate_id_type` in the [store](store.md) configuration.
    
## Uuid

The easiest way is to use an `uuid` as an aggregate ID.
For this, we have the `Uuid` class, which is a simple wrapper for the [ramsey/uuid](https://github.com/ramsey/uuid) library.

You can use it like this:

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    #[IdNormalizer]
    private Uuid $id;
}
```
!!! note

    If you want to use snapshots, then you have to make sure that the aggregate id are normalized. 
    You can find how to do this [here](normalizer.md).
    
You have multiple options for generating an uuid:

```php
use Patchlevel\EventSourcing\Aggregate\Uuid;

$uuid = Uuid::v6();
$uuid = Uuid::v7();
$uuid = Uuid::fromString('d6e8d7a0-4b0b-4e6a-8a9a-3a0b2d9d0e4e');
```
!!! Note

    We offer you the uuid versions 6 and 7, because they are the most suitable for event sourcing.
    More information about uuid versions can be found [here](https://uuid.ramsey.dev/en/stable/rfc4122.html).
    
## Custom ID

If you don't want to use an uuid, you can also use the custom ID implementation.
This is a value object that holds any string.

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Aggregate\CustomId;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    #[IdNormalizer]
    private CustomId $id;
}
```
!!! warning

    If you want to use a custom id that is not an uuid, 
    you need to change the `aggregate_id_type` to `string` in the store configuration.
    More information can be found [here](store.md).
    
!!! note

    If you want to use snapshots, then you have to make sure that the aggregate id are normalized. 
    You can find how to do this [here](normalizer.md).
    
So you can use any string as an id:

```php
use Patchlevel\EventSourcing\Aggregate\CustomId;

$id = CustomId::fromString('my-id');
```
## Implement own ID

Or even better, you create your own aggregate-specific ID class.
This allows you to ensure that the correct id is always used.
The whole thing looks like this:

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;

class ProfileId implements AggregateRootId
{
    private function __construct(
        private readonly string $id,
    ) {
    }

    public function toString(): string
    {
        return $this->id;
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }
}
```
So you can use it like this:

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    #[IdNormalizer]
    private ProfileId $id;
}
```
!!! note

    If you want to use snapshots, then you have to make sure that the aggregate id are normalized. 
    You can find how to do this [here](normalizer.md).
    
We also offer you some traits, so that you don't have to implement the `AggregateRootId` interface yourself.
Here for the uuid:

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Aggregate\RamseyUuidBehaviour;

class ProfileId implements AggregateRootId
{
    use RamseyUuidBehaviour;
}
```
Or for the custom id:

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Aggregate\CustomIdBehaviour;

class ProfileId implements AggregateRootId
{
    use CustomIdBehaviour;
}
```
### Learn more

* [How to create an aggregate](aggregate.md)
* [How to create an event](events.md)
* [How to test an aggregate](testing.md)
* [How to normalize value objects](normalizer.md)
