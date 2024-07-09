# Normalizer

Sometimes you also want to add more complex data in events as payload or in aggregates for the snapshots.
For example DateTime, enums or value objects.
Here you can use the normalizer to define how the data should be saved and loaded.

!!! note

    The underlying system called hydrator exists as a library. 
    You can find out more details [here](https://github.com/patchlevel/hydrator).
    
## Usage

You have a lot of options to use the normalizer.
First of all and simplest, you can let guess the normalizer from the type hint.

```php
final class DTO
{
    public DateTimeImmutable $date;
}
```
Most built-in normalizers can be inferred from the type hint:

* `DateTimeImmutable` => `DateTimeImmutableNormalizer`
* `DateTime` => `DateTimeNormalizer`
* `DateTimeZone` => `DateTimeZoneNormalizer`
* `Enum` => `EnumNormalizer`
* `AggregateRootId` => `IdNormalizer`

!!! note

    `ObjectNormalizer` will not be inferred. You have to specify it yourself.
    This should prevent you from accidentally serializing objects that you don't want to serialize.
    
The other way is to specify the normalizer to the properties directly.
This example is equivalent to the previous one.

```php
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

final class DTO
{
    #[DateTimeImmutableNormalizer]
    public DateTimeImmutable $date;
}
```
And the whole thing also works with property promotion and readonly properties too.

```php
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

final class DTO
{
    public function __construct(
        #[DateTimeImmutableNormalizer]
        public readonly DateTimeImmutable $date,
    ) {
    }
}
```
If you have child entities or value objects, then you can also define the normalizer on class level.
So you don't have to specify it for each property.

```php
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

#[ObjectNormalizer]
final class Item
{
    public function __construct(
        public readonly int $number,
        public readonly DateTimeImmutable $addedAt,
    ) {
    }
}
```
!!! note

    With the `ObjectNormalizer`, you can seraialize and deserialize recursively.
    
### Event

For the event, the properties are normalized to a payload and saved in the DB at the end.
The whole thing is then loaded again from the DB and denormalized in the properties.

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

#[Event('hotel.create')]
final class CreateHotel
{
    public function __construct(
        public readonly string $name,
        #[DateTimeImmutableNormalizer]
        public readonly DateTimeImmutable $createAt,
    ) {
    }
}
```
!!! note

    If you have personal data, you can use [crypto-shredding](personal_data.md).
    
### Aggregate

For the aggregates it is very similar to the events. However, the normalizer is only used for the snapshots.
Here you can determine how the aggregate is saved in the snapshot store at the end.

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Snapshot;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

#[Aggregate('hotel')]
#[Snapshot('default')]
final class Hotel extends BasicAggregateRoot
{
    private string $name;
    #[DateTimeImmutableNormalizer]
    private DateTimeImmutable $createAt;

    // ...
}
```
!!! note

    You can learn more about snapshots [here](snapshots.md).
    
## Built-in Normalizer

For some the standard cases we already offer built-in normalizers.

### Array

If you have a list of objects that you want to normalize, then you must normalize each object individually.
That's what the `ArrayNormalizer` does for you.
In order to use the `ArrayNormaliser`, you still have to specify which normaliser should be applied to the individual
objects. Internally, it basically does an `array_map` and then runs the specified normalizer on each element.

```php
use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

final class DTO
{
    #[ArrayNormalizer(new DateTimeImmutableNormalizer())]
    public array $dates;
}
```
!!! note

    The keys from the arrays are taken over here.
    
### DateTimeImmutable

With the `DateTimeImmutable` Normalizer, as the name suggests,
you can convert DateTimeImmutable objects to a String and back again.

```php
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

final class DTO
{
    #[DateTimeImmutableNormalizer]
    public DateTimeImmutable $date;
}
```
!!! tip

    You can let the hydrator guess the normalizer from the type hint.
    
You can also define the format. Either describe it yourself as a string or use one of the existing constants.
The default is `DateTimeImmutable::ATOM`.

```php
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

final class DTO
{
    #[DateTimeImmutableNormalizer(format: DateTimeImmutable::RFC3339_EXTENDED)]
    public DateTimeImmutable $date;
}
```
!!! note

    You can read about how the format is structured in the [php docs](https://www.php.net/manual/de/datetime.format.php).
    
### DateTime

The `DateTime` Normalizer works exactly like the DateTimeNormalizer. Only for DateTime objects.

```php
use Patchlevel\Hydrator\Normalizer\DateTimeNormalizer;

final class DTO
{
    #[DateTimeNormalizer]
    public DateTime $date;
}
```
!!! tip

    You can let the hydrator guess the normalizer from the type hint.
    
You can also specify the format here. The default is `DateTime::ATOM`.

```php
use Patchlevel\Hydrator\Normalizer\DateTimeNormalizer;

final class DTO
{
    #[DateTimeNormalizer(format: DateTime::RFC3339_EXTENDED)]
    public DateTime $date;
}
```
!!! warning

    It is highly recommended to only ever use DateTimeImmutable objects and the DateTimeImmutableNormalizer. 
    This prevents you from accidentally changing the state of the DateTime and thereby causing bugs.
    
!!! note

    You can read about how the format is structured in the [php docs](https://www.php.net/manual/de/datetime.format.php).
    
### DateTimeZone

To normalize a `DateTimeZone` one can use the `DateTimeZoneNormalizer`.

```php
use Patchlevel\Hydrator\Normalizer\DateTimeZoneNormalizer;

final class DTO
{
    #[DateTimeZoneNormalizer]
    public DateTimeZone $timeZone;
}
```
!!! tip

    You can let the hydrator guess the normalizer from the type hint.
    
### Enum

Backed enums can also be normalized.

```php
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;

final class DTO
{
    #[EnumNormalizer]
    public Status $status;
}
```
!!! tip

    You can let the hydrator guess the normalizer from the type hint.
    
You can also specify the enum class.

```php
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;

final class DTO
{
    #[EnumNormalizer(Status::class)]
    public Status $status;
}
```
### Id

If you have your own AggregateRootId, you can use the `IdNormalizer`.

```php
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

final class DTO
{
    #[IdNormalizer]
    public Uuid $id;
}
```
!!! tip

    You can let the hydrator guess the normalizer from the type hint.
    
Optional you can also define the type of the id.

```php
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

final class DTO
{
    #[IdNormalizer(Uuid::class)]
    public Uuid $id;
}
```
### Object

If you have a complex object that you want to normalize, you can use the `ObjectNormalizer`.
Internally, it uses the `Hydrator` to normalize and denormalize the object.

```php
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

final class DTO
{
    #[ObjectNormalizer]
    public ComplexObject $object;
}
```
Optional you can also define the type of the object.

```php
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

final class DTO
{
    #[ObjectNormalizer(ComplexObject::class)]
    public object $object;
}
```
## Custom Normalizer

Since we only offer normalizers for PHP native things,
you have to write your own normalizers for your own structures, such as value objects.

In our example we have built a value object that should hold a name.

```php
final class Name
{
    public function __construct(private string $value)
    {
        if (strlen($value) < 3) {
            throw new NameIsToShortException($value);
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
```
For this we now need a custom normalizer.
This normalizer must implement the `Normalizer` interface.
You also need to implement a `normalize` and `denormalize` method.
Finally, you have to allow the normalizer to be used as an attribute.

```php
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\Normalizer;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
class NameNormalizer implements Normalizer
{
    public function normalize(mixed $value): string
    {
        if (!$value instanceof Name) {
            throw InvalidArgument::withWrongType(Name::class, $value);
        }

        return $value->toString();
    }

    public function denormalize(mixed $value): Name|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string', $value);
        }

        return new Name($value);
    }
}
```
!!! warning

    The important thing is that the result of Normalize is serializable!
    
Now we can also use the normalizer directly.

```php
final class DTO
{
    #[NameNormalizer]
    public Name $name;
}
```
!!! tip

    Every normalizer, including the custom normalizer, can be used both for the events and for the snapshots.
    
Or define it on class level, so you don't have to specify it for each property.

```php
#[NameNormalizer]
final class Name
{
    /* name logic... */
}
```
## Normalized Name

By default, the property name is used to name the field in the normalized result.
This can be customized with the `NormalizedName` attribute.

```php
use Patchlevel\Hydrator\Attribute\NormalizedName;

final class DTO
{
    #[NormalizedName('profile_name')]
    public string $name;
}
```
The whole thing looks like this

```json
{
  "profile_name": "David"
}
```
!!! tip

    You can also rename properties to events without having a backwards compatibility break by keeping the serialized name.
    
!!! note

    NormalizedName also works for snapshots. 
    But since a snapshot is just a cache, you can also just invalidate it, 
    if you have backwards compatibility break in the property name
    
## Ignore

You can also ignore properties with the `Ignore` attribute.

```php
use Patchlevel\Hydrator\Attribute\Ignore;

final class DTO
{
    #[Ignore]
    public string $name;
}
```
## Learn more

* [How to use the Hydrator](https://github.com/patchlevel/hydrator)
* [How to define aggregates](aggregate.md)
* [How to define events](events.md)
* [How to snapshot aggregates](snapshots.md)
* [How to work with personal data](personal_data.md)
