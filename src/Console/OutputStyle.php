<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\StreamHeader;
use Patchlevel\EventSourcing\Store\StreamStartHeader;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function array_filter;
use function array_values;
use function sprintf;

final class OutputStyle extends SymfonyStyle
{
    public function message(
        EventSerializer $eventSerializer,
        HeadersSerializer $headersSerializer,
        Message $message,
    ): void {
        $event = $message->event();

        try {
            $data = $eventSerializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true]);
        } catch (Throwable $error) {
            $this->error(
                sprintf(
                    'Error while serializing event "%s": %s',
                    $message->event()::class,
                    $error->getMessage(),
                ),
            );

            if ($this->isVeryVerbose()) {
                $this->throwable($error);
            }

            return;
        }

        $customHeaders = array_filter(
            $message->headers(),
            static fn ($header) => !$header instanceof StreamHeader
                && !$header instanceof AggregateHeader
                && !$header instanceof ArchivedHeader
                && !$header instanceof StreamStartHeader,
        );

        $metaHeader = $this->metaHeader($message);
        $streamStart = $message->hasHeader(StreamStartHeader::class);
        $achieved = $message->hasHeader(ArchivedHeader::class);

        $this->title($data->name);
        $this->horizontalTable(
            [
                'stream',
                'playhead',
                'recordedOn',
                'streamStart',
                'archived',
            ],
            [
                [
                    $metaHeader instanceof AggregateHeader ? $metaHeader->streamName() : $metaHeader->streamName,
                    $metaHeader->playhead,
                    $metaHeader->recordedOn?->format('Y-m-d H:i:s'),
                    $streamStart ? 'yes' : 'no',
                    $achieved ? 'yes' : 'no',
                ],
            ],
        );

        if ($customHeaders !== []) {
            $this->block($headersSerializer->serialize(array_values($customHeaders)));
        }

        $this->block($data->payload);
    }

    public function throwable(Throwable $error): void
    {
        $number = 1;

        do {
            $this->error(sprintf('%d) %s', $number, $error->getMessage()));
            $this->block($error->getTraceAsString());

            $number++;
            $error = $error->getPrevious();
        } while ($error !== null);
    }

    private function metaHeader(Message $message): AggregateHeader|StreamHeader
    {
        try {
            return $message->header(AggregateHeader::class);
        } catch (HeaderNotFound) {
            return $message->header(StreamHeader::class);
        }
    }
}
