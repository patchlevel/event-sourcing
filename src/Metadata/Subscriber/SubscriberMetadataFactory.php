<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

interface SubscriberMetadataFactory
{
    /** @param class-string $subscriber */
    public function metadata(string $subscriber): SubscriberMetadata;
}
