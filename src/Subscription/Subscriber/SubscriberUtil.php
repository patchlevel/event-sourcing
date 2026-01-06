<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;

/** @deprecated since 3.15.0 will be removed with 4.0.0 */
trait SubscriberUtil
{
    private static SubscriberMetadataFactory|null $metadataFactory = null;

    public static function setMetadataFactory(SubscriberMetadataFactory $metadataFactory): void
    {
        self::$metadataFactory = $metadataFactory;
    }

    private static function metadataFactory(): SubscriberMetadataFactory
    {
        if (self::$metadataFactory === null) {
            self::$metadataFactory = new AttributeSubscriberMetadataFactory();
        }

        return self::$metadataFactory;
    }

    private function subscriberHelper(): SubscriberHelper
    {
        return new SubscriberHelper(self::metadataFactory());
    }

    private function subscriberId(): string
    {
        return $this->subscriberHelper()->subscriberId($this);
    }
}
