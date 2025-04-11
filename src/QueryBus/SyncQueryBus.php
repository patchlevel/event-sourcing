<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\QueryBus;

use Psr\Log\LoggerInterface;

use function count;
use function is_array;
use function iterator_to_array;

final class SyncQueryBus implements QueryBus
{
    private readonly HandlerProvider $handlerProvider;

    /** @param iterable<HandlerProvider>|HandlerProvider $handlerProviders */
    public function __construct(
        iterable|HandlerProvider $handlerProviders,
        private readonly LoggerInterface|null $logger = null,
    ) {
        if (!$handlerProviders instanceof HandlerProvider) {
            $this->handlerProvider = new ChainHandlerProvider($handlerProviders);
        } else {
            $this->handlerProvider = $handlerProviders;
        }
    }

    /** @throws InvalidQueryHandler */
    public function dispatch(object $query): mixed
    {
        $this->logger?->debug('QueryBus: dispatch query', ['query' => $query::class]);

        $handlers = $this->handlerProvider->handlerForQuery($query::class);

        if (!is_array($handlers)) {
            $handlers = iterator_to_array($handlers);
        }

        $count = count($handlers);

        if ($count === 0) {
            throw InvalidQueryHandler::noHandler($query::class);
        }

        if ($count > 1) {
            throw InvalidQueryHandler::multipleHandler($query::class);
        }

        return ($handlers[0]->callable())($query);
    }
}
