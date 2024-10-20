<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message;

use Generator;
use IteratorAggregate;
use Patchlevel\EventSourcing\Message\Translator\ChainTranslator;
use Patchlevel\EventSourcing\Message\Translator\Translator;
use Traversable;

/** @implements IteratorAggregate<Message> */
final class Pipeline implements IteratorAggregate
{
    /**
     * @param iterable<Message> $messages
     * @param list<Translator>  $translators
     */
    public function __construct(
        private readonly iterable $messages,
        private readonly array $translators = [],
    ) {
    }

    public function appendMiddleware(Translator $translator): self
    {
        return new self(
            $this->messages,
            [...$this->translators, $translator],
        );
    }

    public function prependMiddleware(Translator $translator): self
    {
        return new self(
            $this->messages,
            [$translator, ...$this->translators],
        );
    }

    /** @return Traversable<Message> */
    public function getIterator(): Traversable
    {
        return $this->createGenerator(
            $this->messages,
            new ChainTranslator($this->translators),
        );
    }

    /**
     * @return list<Message>
     */
    public function toArray(): array
    {
        return iterator_to_array($this->getIterator());
    }

    /**
     * @param iterable<Message> $messages
     *
     * @return Generator<Message>
     */
    private function createGenerator(iterable $messages, Translator $translator): Generator
    {
        foreach ($messages as $message) {
            $result = $translator($message);

            foreach ($result as $m) {
                yield $m;
            }
        }
    }
}
