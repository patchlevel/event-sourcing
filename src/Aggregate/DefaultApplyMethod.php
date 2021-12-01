<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use function end;
use function explode;
use function get_class;
use function method_exists;

/**
 * @psalm-require-extends AggregateRoot
 */
trait DefaultApplyMethod
{
    protected function apply(AggregateChanged $event): void
    {
        $method = $this->findApplyMethod($event);

        if (!method_exists($this, $method)) {
            return;
        }

        $this->$method($event);
    }

    private function findApplyMethod(AggregateChanged $event): string
    {
        $classParts = explode('\\', get_class($event));

        return 'apply' . end($classParts);
    }
}
