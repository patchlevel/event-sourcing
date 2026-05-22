<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Architecture;

use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class FinalClassesTest
{
    public function testFinalClasses(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::AllOf(
                    Selector::inNamespace('Patchlevel\EventSourcing'),
                    Selector::NOT(Selector::isAbstract()),
                    Selector::NOT(Selector::isInterface()),
                    Selector::NOT(Selector::classname(Subscriber::class)),
                    Selector::NOT(Selector::classname(Result::class)),
                ),
            )
            ->shouldBeFinal();
    }
}
