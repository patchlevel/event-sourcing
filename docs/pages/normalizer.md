# Normalizer

Sometimes you also want to add more complex data in events as payload or in aggregates for the snapshots.
For example DateTime, enums or value objects. You can do that too.
However, you must define a normalizer for this so that the library knows
how to write this data to the database and load it again.

## Usage

You have to set the normalizer to the properties using the normalize attribute.

```php
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeImmutableNormalizer;

final class DTO 
{
    #[Normalize(new DateTimeImmutableNormalizer())]
    public DateTimeImmutable $date;
}
```

The whole thing also works with property promotion.

```php
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeImmutableNormalizer;

final class DTO 
{
    public function __construct(
        #[Normalize(DateTimeImmutableNormalizer::class)]
        public readonly DateTimeImmutable $date
    ) {}
}
```

### Event

For the event, the properties are normalized to a payload and saved in the DB at the end.
The whole thing is then loaded again from the DB and denormalized in the properties.

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeImmutableNormalizer;

#[Event('hotel.create')]
final class CreateHotel
{
    public function __construct(
        public readonly string $name,
        #[Normalize(new DateTimeImmutableNormalizer())]
        public readonly DateTimeImmutable $createAt
    ) {}
}
```

### Aggregate

For the aggregates it is very similar to the events. However, the normalizer is only used for the snapshots.
Here you can determine how the aggregate is saved in the snapshot store at the end.

```php
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Attribute\Snapshot;
use Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeImmutableNormalizer;

#[Aggregate('hotel')]
#[Snapshot('default')]
final class Hotel extends AggregateRoot
{
    private string $name,
    #[Normalize(new DateTimeImmutableNormalizer())]
    private DateTimeImmutable $createAt

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
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Serializer\Normalizer\ArrayNormalizer;
use Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeImmutableNormalizer;

final class DTO 
{
    #[Normalize(new ArrayNormalizer(new DateTimeImmutableNormalizer()))]
    public array $dates;
}
```

!!! note

    The keys from the arrays are taken over here.

To save yourself this nesting, you can set the flag `list` on the `Normalize` attribute.
In the background, your defined normalizer is then wrapped with the `ArrayNormalizer`.

```php
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeImmutableNormalizer;

final class DTO 
{
    #[Normalize(new DateTimeImmutableNormalizer(), list: true)]
    public array $dates;
}
```

### DateTimeImmutable

With the `DateTimeImmutable` Normalizer, as the name suggests,
you can convert DateTimeImmutable objects to a String and back again.

```php
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeImmutableNormalizer;

final class DTO 
{
    #[Normalize(new DateTimeImmutableNormalizer())]
    public DateTimeImmutable $date;
}
```

You can also define the format. Either describe it yourself as a string or use one of the existing constants.
The default is `DateTimeImmutable::ATOM`.

```php
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeImmutableNormalizer;

final class DTO 
{
    #[Normalize(new DateTimeImmutableNormalizer(format: DateTimeImmutable::RFC3339_EXTENDED))]
    public DateTimeImmutable $date;
}
```

!!! note

    You can read about how the format is structured in the [php docs](https://www.php.net/manual/de/datetime.format.php).

### DateTime

The `DateTime` Normalizer works exactly like the DateTimeNormalizer. Only for DateTime objects.

```php
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeNormalizer;

final class DTO 
{
    #[Normalize(new DateTimeNormalizer())]
    public DateTime $date;
}
```

You can also specify the format here. The default is `DateTime::ATOM`.

```php
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeNormalizer;

final class DTO 
{
    #[Normalize(new DateTimeNormalizer(format: DateTime::RFC3339_EXTENDED))]
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
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeZoneNormalizer;

final class DTO {
    #[Normalize(new DateTimeZoneNormalizer())]
    public DateTimeZone $timeZone;
}
```

### Enum

Backed enums can also be normalized. 
For this, the enum FQCN must also be pass so that the `EnumNormalizer` knows which enum it is.

```php
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Serializer\Normalizer\EnumNormalizer;

final class DTO {
    #[Normalize(new EnumNormalizer(Status::class))]
    public Status $status;
}
```

## Custom Normalizer

Since we only offer normalizers for PHP native things, 
you have to write your own normalizers for your own structures, such as value objects.

In our example we have built a value object that should hold a name.

```php
final class Name
{
    private string $value;
    
    public function __construct(string $value) 
    {
        if (strlen($value) < 3) {
            throw new NameIsToShortException($value);
        }
        
        $this->value = $value;
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
The important thing is that the result of Normalize is serializable.

```php
use Patchlevel\EventSourcing\Serializer\Normalizer\Normalizer;

class NameNormalizer implements Normalizer
{
    public function normalize(mixed $value): string
    {
        if (!$value instanceof Name) {
            throw new InvalidArgumentException();
        }

        return $value->toString();
    }

    public function denormalize(mixed $value): ?Name
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException();
        }

        return new Name($value);
    }
}
```

Now we can also use the normalizer directly by passing it to the Normalize attribute.

```php
use Patchlevel\EventSourcing\Attribute\Normalize;

final class DTO
{
    #[Normalize(new NameNormalizer())]
    public Name $name
}
```

!!! tip

    Every normalizer, including the custom normalizer, can be used both for the events and for the snapshots.


## Normalized Name

By default, the property name is used to name the field in the normalized result.
This can be customized with the `NormalizedName` attribute.

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\NormalizedName;

final class DTO
{
    #[NormalizedName('profile_name')]
    public string $name
}
```

The whole thing looks like this

```php
[
  'profile_name': 'David'
]
```

!!! tip

    You can also rename properties to events without having a backwards compatibility break by keeping the serialized name.

!!! note

    NormalizedName also works for snapshots. 
    But since a snapshot is just a cache, you can also just invalidate it, 
    if you have backwards compatibility break in the property name
