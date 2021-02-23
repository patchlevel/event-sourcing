# Processor

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
