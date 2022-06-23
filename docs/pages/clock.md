# Clock

We have a `ClockInterface` which

## SystemClock

This uses the native system clock - in this case `new DateTimeImmutable()`.

## FrozenClock

This implementation should only be used for the tests. This enables you to freeze the time and with that to have
deterministic tests.

!!! note

    The `ClockInterface` will be PSR-20 compatible as soon at it is published [here](https://github.com/php-fig/fig-standards/blob/master/proposed/clock.md).