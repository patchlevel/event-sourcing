# Processor

The `processor` is a kind of [event bus](./event_bus.md) listener that can execute actions on certain events.
A process can be for example used to send an email when a profile has been created:

## Listener

Here is an example with a listener.

```php
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\Message;

final class SendEmailProcessor
{
    public function __construct(
        private readonly Mailer $mailer
    ) {
    }

    #[Subscribe(ProfileCreated::class)]
    public function __invoke(Message $message): void
    {
        $event = $message->event();

        $this->mailer->send(
            $event->email,
            'Profile created',
            '...'
        );
    }
}
```

!!! tip

    You can find out more about the event bus [here](event_bus.md).
