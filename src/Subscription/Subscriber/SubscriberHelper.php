<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;

final class SubscriberHelper
{
    public function __construct(
        private readonly SubscriberMetadataFactory $metadataFactory = new AttributeSubscriberMetadataFactory(),
    ) {
    }

    public function subscriberId(object $subscriber): string
    {
        return $this->metadata($subscriber)->id;
    }

    private function metadata(object $subscriber): SubscriberMetadata
    {
        return $this->metadataFactory->metadata($subscriber::class);
    }
}
