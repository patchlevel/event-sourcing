<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
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
            static fn ($header) => !$header instanceof StreamNameHeader
                && !$header instanceof PlayheadHeader
                && !$header instanceof RecordedOnHeader
                && !$header instanceof ArchivedHeader
                && !$header instanceof StreamStartHeader,
        );

        $streamName = null;
        $playhead = null;
        $recordedOn = null;

        if ($message->hasHeader(StreamNameHeader::class)) {
            $streamName = $message->header(StreamNameHeader::class)->streamName;
        }

        if ($message->hasHeader(PlayheadHeader::class)) {
            $playhead = $message->header(PlayheadHeader::class)->playhead;
        }

        if ($message->hasHeader(RecordedOnHeader::class)) {
            $recordedOn = $message->header(RecordedOnHeader::class)->recordedOn;
        }

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
                    $streamName,
                    $playhead,
                    $recordedOn?->format('Y-m-d H:i:s'),
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
}
