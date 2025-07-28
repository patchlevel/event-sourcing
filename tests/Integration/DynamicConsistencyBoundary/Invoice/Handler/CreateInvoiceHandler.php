<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Handler;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DCB\DecisionModelBuilder;
use Patchlevel\EventSourcing\DCB\EventAppender;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Command\CreateInvoice;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Event\InvoiceCreated;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Projection\NextInvoiceNumberProjection;

final class CreateInvoiceHandler
{
    public function __construct(
        private readonly DecisionModelBuilder $decisionModelBuilder,
        private readonly EventAppender $eventAppender,
    ) {
    }

    #[Handle]
    public function __invoke(CreateInvoice $command): void
    {
        $state = $this->decisionModelBuilder->build(
            [
                'nextInvoiceNumber' => new NextInvoiceNumberProjection(),
            ],
        );

        $this->eventAppender->append([
            new InvoiceCreated(
                $state['nextInvoiceNumber'],
                $command->money,
            ),
        ], $state->appendCondition);
    }
}
