<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Selector\SelectorInterface;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

use function sprintf;

final class LayerDependenciesTest
{
    public function testAggregateCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('Aggregate'),
            [
                $this->layer('Attribute'),
                $this->layer('Metadata\AggregateRoot'),
                $this->layer('Identifier'),
            ],
        );
    }

    public function testAttributeCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers($this->layer('Attribute'));
    }

    public function testClockCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers($this->layer('Clock'));
    }

    public function testCommandBusCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('CommandBus'),
            [
                $this->layer('Aggregate'),
                $this->layer('Attribute'),
                $this->layer('Metadata\AggregateRoot'),
                $this->layer('Repository'),
                $this->layer('Identifier'),
            ],
        );
    }

    public function testConsoleCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('Console'),
            [
                $this->layer('Aggregate'),
                $this->layer('Message'),
                $this->layer('Metadata\AggregateRoot'),
                $this->layer('Metadata\Event'),
                $this->layer('Schema'),
                $this->layer('Serializer'),
                $this->layer('Store'),
                $this->layer('Subscription'),
            ],
        );
    }

    public function testCryptographyCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('Cryptography'),
            [$this->layer('Schema')],
        );
    }

    public function testEventBusCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('EventBus'),
            [
                $this->layer('Attribute'),
                $this->layer('Message'),
            ],
        );
    }

    public function testMessageCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('Message'),
            [
                $this->layer('Aggregate'),
                $this->layer('Metadata\Message'),
                $this->layer('Serializer'),
                $this->layer('Store'),
            ],
        );
    }

    public function testMetadataCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers($this->metadataLayer());
    }

    public function testMetadataAggregateCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('Metadata\AggregateRoot'),
            [
                $this->layer('Aggregate'),
                $this->layer('Attribute'),
                $this->metadataLayer(),
            ],
        );
    }

    public function testMetadataEventCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('Metadata\Event'),
            [
                $this->layer('Attribute'),
                $this->metadataLayer(),
            ],
        );
    }

    public function testMetadataMessageCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('Metadata\Message'),
            [
                $this->layer('Aggregate'),
                $this->layer('Attribute'),
                $this->metadataLayer(),
                $this->layer('Store'),
            ],
        );
    }

    public function testMetadataSubscriberCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('Metadata\Subscriber'),
            [
                $this->layer('Attribute'),
                $this->metadataLayer(),
                $this->layer('Subscription'),
            ],
        );
    }

    public function testQueryBusCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('QueryBus'),
            [$this->layer('Attribute')],
        );
    }

    public function testRepositoryCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('Repository'),
            [
                $this->layer('Aggregate'),
                $this->layer('Clock'),
                $this->layer('Message'),
                $this->layer('Metadata\AggregateRoot'),
                $this->layer('Metadata\Event'),
                $this->layer('EventBus'),
                $this->layer('Snapshot'),
                $this->layer('Store'),
            ],
        );
    }

    public function testSchemaCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers($this->layer('Schema'));
    }

    public function testSerializerCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('Serializer'),
            [
                $this->layer('Aggregate'),
                $this->layer('Cryptography'),
                $this->layer('Metadata\Event'),
            ],
        );
    }

    public function testSnapshotCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('Snapshot'),
            [
                $this->layer('Aggregate'),
                $this->layer('Cryptography'),
                $this->layer('Metadata\AggregateRoot'),
            ],
        );
    }

    public function testStoreCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('Store'),
            [
                $this->layer('Clock'),
                $this->layer('Message'),
                $this->metadataLayer(),
                $this->layer('Schema'),
                $this->layer('Serializer'),
            ],
        );
    }

    public function testSubscriptionCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers(
            $this->layer('Subscription'),
            [
                $this->layer('Aggregate'),
                $this->layer('Attribute'),
                $this->layer('Clock'),
                $this->layer('Message'),
                $this->layer('Metadata\Event'),
                $this->layer('Metadata\Subscriber'),
                $this->layer('Repository'),
                $this->layer('Schema'),
                $this->layer('Store'),
            ],
        );
    }

    public function testTestCanOnlyDependOnAllowedLayers(): Rule
    {
        return $this->layerCanOnlyDependOnAllowedLayers($this->layer('Test'));
    }

    /** @param array<SelectorInterface> $allowedInternalLayers */
    private function layerCanOnlyDependOnAllowedLayers(
        SelectorInterface $layer,
        array $allowedInternalLayers = [],
    ): Rule {
        return PHPat::rule()
            ->classes($layer)
            ->canOnlyDependOn()
            ->classes(
                Selector::NOT(Selector::inNamespace('Patchlevel\EventSourcing')), // only internal deps
                $layer, // allow itself
                ...$allowedInternalLayers,
            );
    }

    private function layer(string $layer): SelectorInterface
    {
        return Selector::inNamespace(sprintf('Patchlevel\\EventSourcing\\%s', $layer));
    }

    private function metadataLayer(): SelectorInterface
    {
        return Selector::AllOf(
            $this->layer('Metadata'),
            Selector::NOT($this->layer('Metadata\AggregateRoot')),
            Selector::NOT($this->layer('Metadata\Event')),
            Selector::NOT($this->layer('Metadata\Message')),
            Selector::NOT($this->layer('Metadata\Subscriber')),
        );
    }
}
