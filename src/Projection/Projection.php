<?php declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

interface Projection extends MessageSubscriberInterface
{
    /**
     * @return iterable<class-string, string|array<string, mixed>>
     */
    public static function getHandledMessages(): iterable;

    public function drop(): void;
}
