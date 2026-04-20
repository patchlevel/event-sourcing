<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Container;

use Patchlevel\EventSourcing\CommandBus\HandlerProvider;
use Patchlevel\EventSourcing\Container\ConfigurationNormalizer;
use Patchlevel\EventSourcing\Container\ConfigurationShapeGenerator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigurationShapeGenerator::class)]
final class ConfigurationShapeGeneratorTest extends TestCase
{
    public function testGenerate(): void
    {
        $tree = ConfigurationNormalizer::named('Config', ConfigurationNormalizer::struct([
            'type' => ConfigurationNormalizer::named('Type', ConfigurationNormalizer::enum(['a', 'b'], 'a')),
            'definition' => ConfigurationNormalizer::named('Definition', ConfigurationNormalizer::struct([
                'service' => ConfigurationNormalizer::requiredString(),
                'limit' => ConfigurationNormalizer::int(null, 1),
            ])),
            'feature' => ConfigurationNormalizer::toggle(false, [
                'decorators' => ConfigurationNormalizer::services(MessageDecorator::class),
                'providers' => ConfigurationNormalizer::services(HandlerProvider::class),
                'exceptions' => ConfigurationNormalizer::typed(
                    'list<class-string<\Throwable>>',
                    ConfigurationNormalizer::stringList(),
                ),
            ]),
            'parameters' => ConfigurationNormalizer::map(ConfigurationNormalizer::variable()),
        ]));

        $source = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace Patchlevel\EventSourcing\Container;

            use Patchlevel\EventSourcing\CommandBus\HandlerProvider as CommandBusHandlerProvider;
            use Patchlevel\EventSourcing\Repository\AggregateOutdated;

            use function sprintf;

            /**
             * An example configuration.
             *
             * @phpstan-type Outdated string
             * @phpstan-import-type Node from ConfigurationNormalizer
             */
            final class ExampleConfiguration
            {
            }

            PHP;

        self::assertSame(
            <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace Patchlevel\EventSourcing\Container;

            use Patchlevel\EventSourcing\CommandBus\HandlerProvider as CommandBusHandlerProvider;
            use Patchlevel\EventSourcing\Repository\AggregateOutdated;
            use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
            use Throwable;

            use function sprintf;

            /**
             * An example configuration.
             *
             * @phpstan-type Type 'a'|'b'
             * @phpstan-type Definition array{service: string, limit?: positive-int|null}
             * @phpstan-type NormalizedDefinition array{service: string, limit: positive-int|null}
             * @phpstan-type Config array{
             *     type?: Type,
             *     definition?: Definition,
             *     feature?: bool|array{
             *         enabled?: bool,
             *         decorators?: list<MessageDecorator|string>,
             *         providers?: list<CommandBusHandlerProvider|string>,
             *         exceptions?: list<class-string<Throwable>>,
             *     },
             *     parameters?: array<string, mixed>,
             * }
             * @phpstan-type NormalizedConfig array{
             *     type: Type,
             *     definition: NormalizedDefinition,
             *     feature: array{
             *         enabled: bool,
             *         decorators: list<MessageDecorator|string>,
             *         providers: list<CommandBusHandlerProvider|string>,
             *         exceptions: list<class-string<Throwable>>,
             *     },
             *     parameters: array<string, mixed>,
             * }
             * @phpstan-import-type Node from ConfigurationNormalizer
             */
            final class ExampleConfiguration
            {
            }

            PHP,
            (new ConfigurationShapeGenerator())->generate($tree, $source),
        );
    }
}
