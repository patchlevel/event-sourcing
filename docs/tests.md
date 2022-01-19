# Tests

The aggregates can also be tested very well. 
You can test whether certain events have been thrown 
or whether the state is set up correctly when the aggregate is set up again via the events.

```php
use PHPUnit\Framework\TestCase;

final class ProfileTest extends TestCase
{
    public function testCreateProfile(): void
    {
        $profile = Profile::createProfile(ProfileId::generate(), Email::fromString('foo@email.com'));

        $events = $profile->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ProfileCreated::class, $events[0]);
        self::assertEquals('foo@email.com', $profile->email()->toString());
    }

    public function testRebuild(): void
    {
        $id = ProfileId::generate();

        $events = [
            ProfileCreated::raise($id, Email::fromString('foo@email.com'))->recordNow(1),
        ];

        $profile = Profile::createFromEventStream($events);

        self::assertEquals('foo@email.com', $profile->email()->toString());
    }
}
```
