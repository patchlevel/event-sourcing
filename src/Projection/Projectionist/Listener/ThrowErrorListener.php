<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist\Listener;

use Patchlevel\EventSourcing\Projection\Projectionist\Event\ProjectorErrorEvent;
use Patchlevel\EventSourcing\Projection\Projectionist\ProjectionistError;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ThrowErrorListener implements EventSubscriberInterface
{
    public function onProjectorError(ProjectorErrorEvent $event): void
    {
        throw new ProjectionistError(
            $event->projector,
            $event->projection,
            $event->error,
        );
    }

    /** @return array<class-string, string> */
    public static function getSubscribedEvents(): array
    {
        return [ProjectorErrorEvent::class => 'onProjectorError'];
    }
}
