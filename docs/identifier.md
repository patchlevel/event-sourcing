# Identifier

Identifiers are small, immutable value objects that represent IDs across the library in a type‑safe and consistent way. They are primarily used for aggregate root IDs, but can also be applied anywhere a stable string identifier is required (snapshots, repositories, your own domain objects, etc.).

All identifiers must implement `Patchlevel\EventSourcing\Identifier\Identifier` and provide a stable string representation for storage and serialization.

## Interface

```php
interface Identifier
{
    public function toString(): string;

    public static function fromString(string $id): static;
}
```
The string value returned by `toString()` is what gets persisted (e.g. in the event store) and serialized. `fromString()` reconstructs the value object from that string.

## Built‑in identifiers

We provide two ready‑to‑use implementations:

### `Uuid`

`Uuid` wraps [ramsey/uuid](https://github.com/ramsey/uuid) and uses UUID v7 by default, which is a good fit for event‑sourcing.

```php
use Patchlevel\EventSourcing\Identifier\Uuid;

$uuid = Uuid::generate();
$uuid = Uuid::fromString('d6e8d7a0-4b0b-4e6a-8a9a-3a0b2d9d0e4e');
```

:::note
UUID v7 provides k‑sortable identifiers that work well with append‑only streams and database indexes. See the ramsey docs for details.
:::
    
### `CustomId`

`CustomId` is a minimal string‑backed identifier. Use it if you want full control over the string format or if your IDs are provided by an external system.

```php
use Patchlevel\EventSourcing\Identifier\CustomId;

$id = CustomId::fromString('my-id');
```
## Domain‑specific identifiers

For better domain modeling, define your own identifier types by implementing `Identifier` and encapsulating creation rules and validation. Two traits are available to make this easy:

- `RamseyUuidV7Behaviour` for UUID v7 identifiers
- `CustomIdBehaviour` for string‑backed identifiers

```php
use Patchlevel\EventSourcing\Identifier\Identifier;
use Patchlevel\EventSourcing\Identifier\RamseyUuidV7Behaviour;

final class ProfileId implements Identifier
{
    use RamseyUuidV7Behaviour;
}
```
or

```php
use Patchlevel\EventSourcing\Identifier\CustomIdBehaviour;
use Patchlevel\EventSourcing\Identifier\Identifier;

final class OrderNumber implements Identifier
{
    use CustomIdBehaviour;
}
```
## Using identifiers with aggregates

Aggregates expose and persist their IDs as `Identifier` instances. You can choose the concrete implementation that fits your domain.

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Identifier\Uuid;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    private Uuid $id;
}
```
Or use your domain‑specific identifier:

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;
}
```
## Serialization and normalization

Identifiers integrate with the serializer via `IdNormalizer`. The `Identifier` interface is annotated so that instances are automatically normalized to strings and denormalized back to the correct class.

This means you can safely use identifier types in command payloads, events, or snapshots without writing custom normalizers.

## Testing

For deterministic tests involving UUIDs, use `FakeRamseyUuidFactory` to generate predictable UUID v7 values.

## Notes

- Identifiers are stored as strings in the backing store, but you should always use concrete identifier types in your domain code for type‑safety and clarity.
- You can use identifiers beyond aggregates wherever an opaque, stable ID is needed.

### Learn more

* [How to create an aggregate](aggregate.md)
* [How to create an event](events.md)
* [How to test an aggregate](testing.md)
