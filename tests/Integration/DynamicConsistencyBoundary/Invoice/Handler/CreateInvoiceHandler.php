<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Handler;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DecisionModel\Decision;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Command\CreateInvoice;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Event\InvoiceCreated;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Projection\NextInvoiceNumberProjection;

final class CreateInvoiceHandler
{
    #[Handle]
    public function __invoke(CreateInvoice $command, Decision $decision): void
    {
        $decision->append([
            new InvoiceCreated(
                $decision->read(new NextInvoiceNumberProjection()),
                $command->money,
            ),
        ]);
    }
}
