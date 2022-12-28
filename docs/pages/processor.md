# Processor

The `processor` is a kind of [event bus](./event_bus.md) listener that can execute actions on certain events.
A process can be for example used to send an email when a profile has been created:

## Listener

Here is an example with a listener.

```php
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\Message;

final class SendEmailProcessor implements Listener
{
    public function __construct(
        private readonly Mailer $mailer
    ) {
    }

    public function __invoke(Message $message): void
    {
        $event = $message->event();
    
        if (!$event instanceof ProfileCreated) {
            return;
        }

        $this->mailer->send(
            $event->email,
            'Profile created',
            '...'
        );
    }
}
```

!!! warning

    If you only want to listen to certain events, 
    then you have to check it in the `__invoke` method or use the subscriber.

!!! tip

    You can find out more about the event bus [here](event_bus.md).


## Subscriber

You can also create the whole thing as a subscriber too.

```php
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Subscriber;

final class SendEmailProcessor extends Subscriber
{
    public function __construct(
        private readonly Mailer $mailer
    ) {
    }

    #[Handle(ProfileCreated::class)]
    public function onProfileCreated(Message $message): void
    {
        $this->mailer->send(
            $message->event()->email,
            'Profile created',
            '...'
        );
    }
}
```

!!! tip

    You can find out more about the event bus [here](event_bus.md).
