<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use Throwable;

use function sprintf;

final class SerializationNotPossible extends SerializeException
{
    private object $event;

    public function __construct(object $event, ?Throwable $previous = null)
    {
        $this->event = $event;

        parent::__construct(
            sprintf(
                'serialization of "%s" is not possible',
                $event::class
            ),
            0,
            $previous
        );
    }

    public function event(): object
    {
        return $this->event;
    }
}
