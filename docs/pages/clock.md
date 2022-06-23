# Clock

We have a `ClockInterface` which enables you to replace the actual clock implementation in your services for testing
purposes. We are using this clock to create the `recorded_on` datetime for the events.

## SystemClock

This uses the native system clock to return the DateTimeImmutable instance - in this case `new DateTimeImmutable()`.

```php
use Patchlevel\EventSourcing\Clock\SystemClock;

$clock = new SystemClock();
$date = $clock->now(); // get the actual datetime 
$date2 = $clock->now();

$date == $date2 // false
$date === $date2 // false
```

## FrozenClock

This implementation should only be used for the tests. This enables you to freeze the time and with that to have
deterministic tests.

```php
use Patchlevel\EventSourcing\Clock\FrozenClock;

$date = new DateTimeImmutable();

$clock = new FrozenClock($date);
$frozenDate = $clock->now(); // gets the date provided before 

$date == $frozenDate // true
$date === $frozenDate // false, since it's not identity identical due to internally cloning the frozen datetime
```

!!! note

    The `ClockInterface` will be PSR-20 compatible as soon at it is published [here](https://github.com/php-fig/fig-standards/blob/master/proposed/clock.md).