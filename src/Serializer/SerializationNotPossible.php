<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Throwable;

use function sprintf;

final class SerializationNotPossible extends SerializeException
{
    private AggregateChanged $event;

    public function __construct(AggregateChanged $event, ?Throwable $previous = null)
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

    public function event(): AggregateChanged
    {
        return $this->event;
    }
}
