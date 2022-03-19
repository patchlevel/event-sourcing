<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\DefaultHydrator;
use Patchlevel\EventSourcing\Serializer\Hydrator;

use function array_key_exists;

final class ClassRenameMiddleware implements Middleware
{
    /** @var array<class-string, class-string> */
    private array $classes;
    private Hydrator $hydrator;

    /**
     * @param array<class-string, class-string> $classes
     */
    public function __construct(array $classes, ?Hydrator $hydrator = null)
    {
        $this->classes = $classes;
        $this->hydrator = $hydrator ?? new DefaultHydrator();
    }

    /**
     * @return list<Message>
     */
    public function __invoke(Message $message): array
    {
        $event = $message->event();
        $class = $event::class;

        if (!array_key_exists($class, $this->classes)) {
            return [$message];
        }

        $newClass = $this->classes[$class];
        $newEvent = $this->hydrator->hydrate(
            $newClass,
            $this->hydrator->extract($event)
        );

        return [
            new Message(
                $message->aggregateClass(),
                $message->aggregateId(),
                $message->playhead(),
                $newEvent,
                $message->recordedOn()
            ),
        ];
    }
}
