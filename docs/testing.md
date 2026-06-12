# Tests

The library's design promotes easily testable code, and we offer several helpers to simplify the testing process even
further. If you need additional support, we also provide
a [PHPUnit testing library](https://github.com/patchlevel/event-sourcing-phpunit) to make testing even more convenient.

## Testing with patchlevel/event-sourcing-phpunit

### Aggregate Unit Tests

There is a special `TestCase` for aggregate tests that you can extend. By extending `AggregateRootTestCase`, you can use
the given/when/then notation, making the test's purpose clear. When extending this class, you must implement a method
that provides the fully qualified class name (FQCN) of the aggregate you want to test.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\AggregateRootTestCase;

final class ProfileTest extends AggregateRootTestCase
{
    protected function aggregateClass(): string
    {
        return Profile::class;
    }

    public function testCreateProfile(): void
    {
        $this
            ->when(static fn () => Profile::createProfile(new CreateProfile(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de'))))
            ->then(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
                static function (Profile $profile): void {
                    self::assertSame('1', $profile->id()->toString());
                    self::assertSame('hq@patchlevel.de', $profile->email()->toString());
                    self::assertSame(0, $profile->visited());
                },
            );
    }
}
```
In addition to expected events, you can pass `Closure`s to `then`.
The closure receives the aggregate instance after `when`, so you can run PHPUnit assertions on aggregate state.
This support is available since `patchlevel/event-sourcing-phpunit` `1.5`.

You can also prepare the aggregate with events to set it to a specific state and then test whether it behaves as
expected.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\AggregateRootTestCase;

final class ProfileTest extends AggregateRootTestCase
{
    // protected function aggregateClass(): string;

    public function testBehaviour(): void
    {
        $this
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(static fn (Profile $profile) => $profile->visitProfile(ProfileId::fromString('2')))
            ->then(
                new ProfileVisited(ProfileId::fromString('2')),
                static function (Profile $profile): void {
                    self::assertSame('1', $profile->id()->toString());
                    self::assertSame('hq@patchlevel.de', $profile->email()->toString());
                    self::assertSame(1, $profile->visited());
                },
            );
    }
}
```
#### Using Commandbus like syntax

When using the command bus and the `#[Handle]` attributes in your aggregate, you can directly provide the command to the
`when` method.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\AggregateRootTestCase;

final class ProfileTest extends AggregateRootTestCase
{
    // protected function aggregateClass(): string;

    public function testBehaviour(): void
    {
        $this
            ->when(new CreateProfile(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')))
            ->then(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
                static fn (Profile $profile) => self::assertSame('hq@patchlevel.de', $profile->email()->toString()),
            );
    }
}
```
If additional parameters are required beyond the command, they can be provided as extra arguments for `when`.
In this example, a string is needed, which will be passed directly to the event.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\AggregateRootTestCase;

final class ProfileTest extends AggregateRootTestCase
{
    // protected function aggregateClass(): string;

    public function testBehaviour(): void
    {
        $this
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(
                new VisitProfile(ProfileId::fromString('2')),
                'Extra Parameter / Dependency',
            )
            ->then(
                new ProfileVisited(ProfileId::fromString('2'), 'Extra Parameter / Dependency'),
                static fn (Profile $profile) => self::assertSame(1, $profile->visited()),
            );
    }
}
```
### Subscriber Tests

For testing a subscriber, there is a utility class available. Using `SubscriberUtilities` provides several DX features
that simplify testing.

First, you need to specify the subscriptions you want to test when initializing the utility class. Once set up, you can
call three methods:

- `executeSetup`
- `executeRun`
- `executeTeardown`

These methods automatically invoke the appropriate functions defined via attributes.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\SubscriberUtilities;

final class ProfileSubscriberTest extends TestCase
{
    use SubscriberUtilities;

    public function testProfileCreated(): void
    {
        $subscriber = new ProfileSubscriber(/* inject deps or mock tests as needed */);

        $util = new SubscriberUtilities($subscriber);
        $util->executeSetup();
        $util->executeRun(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hq@patchlevel.de'),
            ),
        );
        $util->executeTeardown();

        self::assertSame(3, $subscriber->count);
    }
}
```
## Tests with DateTime using a Clock

You should not instantiate the `DateTimeImmutable` directly in the aggregate.
Instead, you should pass a `Clock` to the aggregate and use this to get the current time.
This allows you to test the aggregate with a fixed time.

```php
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\PhpUnit\Test\AggregateRootTestCase;

final class ProfileTest extends AggregateRootTestCase
{
    // protected function aggregateClass(): string;

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

        $this
            ->given(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')))
            ->when(
                new ChangeEmail(ProfileId::fromString('1'), Email::fromString('new-hq@patchlevel.de')),
                $clock,
            )
            ->then(
                new EmailChanged(
                    ProfileId::fromString('1'),
                    Email::fromString('new-hq@patchlevel.de'),
                    new DateTimeImmutable('2021-01-01 00:00:10'),
                ),
                static fn (Profile $profile) => self::assertSame('new-hq@patchlevel.de', $profile->email()->toString()),
            );
    }
}
```
:::note
You can find out more about the clock [here](clock.md).
:::

:::tip
You can use the FreezeClock in you integration tests to test the time-based behavior of your application.
:::

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
:::warning
The `IncrementalRamseyUuidFactory` is only for testing purposes
and supports only the version 7 what is used by the library.
:::
