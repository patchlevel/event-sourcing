# Normalizer

Sometimes you also want to add more complex data as a payload. For example DateTime or value objects.
You can do that too. However, you must define a normalizer for this 
so that the library knows how to write this data to the database and load it again.

## Usage

// todo

## Built-in Normalizer

// todo

### Array

// todo

### DateTimeImmutable

// todo

### DateTime

// todo

### DateTimeZone

// todo

### Enum

// todo

## Custom Normalizer

// todo

In our example we build a Name Value Object:

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

And for that we need our own normalizer. 
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

We can use all of this with the `Normalize` attribute as follows. 
The attribute must be set over the property to which it is to be applied.

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Normalize;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        #[Normalize(NameNormalizer::class)]
        public readonly Name $name
    ) {}
}
```

In the example we simply specified the class. But we can also instantiate the normalizer and pass parameters.
That doesn't make sense at this point, but here's the example:

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Normalize;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        #[Normalize(new NameNormalizer('foo'))]
        public readonly Name $name
    ) {}
}
```

## Normalized Name

By default, the property name is used to name the field in json. 
This can be customized with the `NormalizedName` attribute.

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\NormalizedName;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        #[NormalizedName('profile_name')]
        public readonly string $name
    ) {}
}
```

The whole thing looks like this

```php
[
  'profile_name': 'David'
]
```
