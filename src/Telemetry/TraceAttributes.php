<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

/**
 * Span attribute names.
 *
 * Where the OpenTelemetry messaging semantic conventions define a matching attribute,
 * that name is used. Everything else is namespaced under event_sourcing.
 *
 * @see https://opentelemetry.io/docs/specs/semconv/messaging/messaging-spans/
 */
final class TraceAttributes
{
    public const MESSAGING_SYSTEM = 'messaging.system';
    public const MESSAGING_OPERATION_NAME = 'messaging.operation.name';
    public const MESSAGING_OPERATION_TYPE = 'messaging.operation.type';
    public const MESSAGING_DESTINATION_NAME = 'messaging.destination.name';
    public const MESSAGING_MESSAGE_ID = 'messaging.message.id';
    public const MESSAGING_MESSAGE_CONVERSATION_ID = 'messaging.message.conversation_id';
    public const MESSAGING_BATCH_MESSAGE_COUNT = 'messaging.batch.message_count';

    public const EVENT_NAME = 'event_sourcing.event.name';
    public const CAUSATION_ID = 'event_sourcing.causation_id';
    public const AGGREGATE_NAME = 'event_sourcing.aggregate.name';
    public const AGGREGATE_ID = 'event_sourcing.aggregate.id';
    public const SUBSCRIPTION_ID = 'event_sourcing.subscription.id';
    public const SUBSCRIPTION_GROUP = 'event_sourcing.subscription.group';
    public const SUBSCRIPTION_PROCESSED_MESSAGES = 'event_sourcing.subscription.processed_messages';
    public const SUBSCRIPTION_FINISHED = 'event_sourcing.subscription.finished';
    public const ERROR_COUNT = 'event_sourcing.error.count';
    public const COMMAND_NAME = 'event_sourcing.command.name';
    public const QUERY_NAME = 'event_sourcing.query.name';

    public const SYSTEM = 'patchlevel_event_sourcing';

    public const OPERATION_TYPE_SEND = 'send';
    public const OPERATION_TYPE_PROCESS = 'process';
}
