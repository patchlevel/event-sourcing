# Processor

A `processor` is an event listener who listens to recorded events.

In this library there is a core module called `EventBus`. 
For all events that are persisted (when the `save` method has been executed on the repository), 
the event will be dispatched to the EventBus. All listeners are then called for each event.

A process can be for example used to send an email when a profile has been created:

```php
<?php

declare(strict_types=1);

namespace App\Profile\Listener;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\Listener;

final class SendEmailListener implements Listener
{
    private Mailer $mailer;

    private function __construct(Mailer $mailer) 
    {
        $this->mailer = $mailer;
    }

    public function __invoke(AggregateChanged $event): void
    {
        if (!$event instanceof ProfileCreated) {
            return;
        }

        $this->mailer->send(
            $event->email(),
            'Profile created',
            '...'
        );
    }
}
```

> :warning: If you only want to listen to certain events, then you have to check it in the `__invoke` method.

## Register Processor

If you are using the `DefaultEventBus`, you can register the listener as follows.

```php
$eventStream = new DefaultEventBus();
$eventStream->addListener(new SendEmailListener($mailer));
```