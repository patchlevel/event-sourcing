<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

/**
 * A listener stopped the processing of a subscription for the rest of the run, see OnHandleMessage::$stop.
 *
 * @internal
 */
final class ProcessingStopped
{
}
