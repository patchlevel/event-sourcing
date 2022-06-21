# Tests

The aggregates can also be tested very well. 
You can test whether certain events have been thrown 
or whether the state is set up correctly when the aggregate is set up again via the events.

```php
use PHPUnit\Framework\TestCase;

final class ProfileTest extends TestCase
{
    use AggregateTestHelper;

    public function testCreateProfile(): void
    {
        $id = ProfileId::generate();
        $profile = Profile::createProfile($id, Email::fromString('foo@email.com'));

        self::assertRecordedEvents(
            $profile, 
            [
                new ProfileCreated($id, Email::fromString('foo@email.com')),        
            ]
        );

        self::assertEquals('foo@email.com', $profile->email()->toString());
    }
    
    public function testChangeName(): void
    {
        $id = ProfileId::generate();
        $profile = self::createAggregateFromEvents([
            new ProfileCreated($id, Email::fromString('foo@email.com')),
        ]);
        
        $profile->changeEmail(Email::fromString('bar@email.com'));
        
        self::assertRecordedEvents(
            $profile, 
            [
                new EmailChanged(Email::fromString('bar@email.com')),        
            ]
        );

        self::assertEquals('bar@email.com', $profile->email()->toString());
    }
}
```
