<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message;

use Generator;
use IteratorAggregate;
use Patchlevel\EventSourcing\Message\Translator\ChainTranslator;
use Patchlevel\EventSourcing\Message\Translator\Translator;
use Traversable;

use function array_values;
use function iterator_to_array;

/** @implements IteratorAggregate<int, Message> */
final class Pipe implements IteratorAggregate
{
    private Translator $translator;

    /** @param iterable<Message> $messages */
    public function __construct(
        private readonly iterable $messages,
        Translator ...$translators,
    ) {
        $this->translator = new ChainTranslator($translators);
    }

    /** @return Traversable<Message> */
    public function getIterator(): Traversable
    {
        return $this->createGenerator(
            $this->messages,
            $this->translator,
        );
    }

    /** @return list<Message> */
    public function toArray(): array
    {
        return array_values(
            iterator_to_array($this->getIterator()),
        );
    }

    /**
     * @param iterable<Message> $messages
     *
     * @return Generator<Message>
     */
    private function createGenerator(iterable $messages, Translator $translator): Generator
    {
        foreach ($messages as $message) {
            foreach ($translator($message) as $translatedMessage) {
                yield $translatedMessage;
            }
        }
    }
}
