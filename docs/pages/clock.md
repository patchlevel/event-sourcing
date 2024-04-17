# Clock

We are using the clock to get the current datetime. This is needed to create the `recorded_on` datetime for the event stream.
We have two implementations of the clock, one for the production and one for the tests.
But you can also create your own implementation that is PSR-20 compatible.
For more information see [here](https://github.com/php-fig/fig-standards/blob/master/proposed/clock.md).

## SystemClock

This uses the native system clock to return the `DateTimeImmutable` instance.

```php
use Patchlevel\EventSourcing\Clock\SystemClock;

$clock = new SystemClock();
$date = $clock->now(); // get the actual datetime
$date2 = $clock->now();

// $date == $date2 => false
// $date === $date2 => false
```
## FrozenClock

This implementation should only be used for the tests. This enables you to freeze the time and with that to have
deterministic tests.

```php
use Patchlevel\EventSourcing\Clock\FrozenClock;

$date = new DateTimeImmutable();

$clock = new FrozenClock($date);
$frozenDate = $clock->now(); // gets the date provided before

// $date == $frozenDate => true
// $date === $frozenDate => false
```
The `FrozenClock` can also be updated with a new date, so you can test a jump in time.

```php
use Patchlevel\EventSourcing\Clock\FrozenClock;

$firstDate = new DateTimeImmutable();
$clock = new FrozenClock($firstDate);

$secondDate = new DateTimeImmutable();
$clock->update($secondDate);

$frozenDate = $clock->now();

// $firstDate == $frozenDate => false
// $secondDate == $frozenDate => true
```
Or you can use the `sleep` method to simulate a time jump.

```php
use Patchlevel\EventSourcing\Clock\FrozenClock;

$firstDate = new DateTimeImmutable();
$clock = new FrozenClock($firstDate);

$clock->sleep(10); // sleep 10 seconds
```
!!! note

    The instance of the frozen datetime will be cloned internally, so the it's not the same instance but equals.
    
## Learn more

* [How to test with datetime](testing.md)
* [How to normalize datetime](normalizer.md)
* [How to use messages](message.md)
