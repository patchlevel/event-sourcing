# Tests

The library is designed to be easily testable.
We provide you with a few helpers to make testing easier
and some tips on how to test your application.

## Aggregate Unit Tests

The aggregates can also be tested very well.
You can test whether certain events have been recorded
or whether the state is set up correctly when the aggregate is set up again via the events.

```php
use PHPUnit\Framework\TestCase;

final class ProfileTest extends TestCase
{
    public function testCreateProfile(): void
    {
        $id = ProfileId::generate();
        $profile = Profile::createProfile($id, Email::fromString('foo@email.com'));

        self::assertEquals(
            $profile->releaseEvents(),
            [
                new ProfileCreated($id, Email::fromString('foo@email.com')),
            ],
        );

        self::assertEquals('foo@email.com', $profile->email()->toString());
    }
}
```
You can also prepare the aggregate with events to a specific state.
And then test whether the aggregate behaves as expected.

```php
use PHPUnit\Framework\TestCase;

final class ProfileTest extends TestCase
{
    public function testChangeName(): void
    {
        $id = ProfileId::generate();

        $profile = Profile::createFromEvents([
            new ProfileCreated($id, Email::fromString('foo@email.com')),
        ]);

        $profile->changeEmail(Email::fromString('bar@email.com'));

        self::assertEquals(
            $profile->releaseEvents(),
            [
                new EmailChanged(Email::fromString('bar@email.com')),
            ],
        );

        self::assertEquals('bar@email.com', $profile->email()->toString());
    }
}
```
## Tests with DateTime

You should not instantiate the `DateTimeImmutable` directly in the aggregate.
Instead, you should pass a `Clock` to the aggregate and use this to get the current time.
This allows you to test the aggregate with a fixed time.

```php
use Patchlevel\EventSourcing\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;

final class ProfileTest extends TestCase
{
    public function testCreateProfile(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01 00:00:00'));

        $profile = Profile::createProfile(
            ProfileId::generate(),
            Email::fromString('info@patchlevel.de'),
            $clock,
        );

        $clock->sleep(10);

        $profile->changeEmail(Email::fromString('info@patchlevel.de'));
    }
}
```
!!! note

    You can find out more about the clock [here](clock).
    
!!! tip

    You can use the FreezeClock in you integration tests to test the time-based behavior of your application.
    
## Tests with UUID

Uuids are randomly generated and can be a problem in tests.
If you want deterministic tests, you can use the `IncrementalRamseyUuidFactory` from the library.

```php
use Patchlevel\EventSourcing\Test\IncrementalRamseyUuidFactory;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ProfileTest extends TestCase
{
    public function setUp(): void
    {
        Uuid::setFactory(new IncrementalRamseyUuidFactory());
    }

    public function testCreateProfile(): void
    {
        $id1 = ProfileId::generate(); // 10000000-7000-0000-0000-000000000001
        $id2 = ProfileId::generate(); // 10000000-7000-0000-0000-000000000002
    }
}
```
!!! warning

    The `IncrementalRamseyUuidFactory` is only for testing purposes
    and supports only the version 7 what is used by the library.
    