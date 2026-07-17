<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata;

use Patchlevel\EventSourcing\Metadata\ClassFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function is_dir;
use function mkdir;
use function rmdir;
use function symlink;
use function sys_get_temp_dir;
use function unlink;

#[CoversClass(ClassFinder::class)]
final class ClassFinderTest extends TestCase
{
    public function testEmpty(): void
    {
        $finder = new ClassFinder();
        $classes = $finder->findClassNames([__DIR__ . '/../../../docs']);

        self::assertCount(0, $classes);
    }

    public function testNoPaths(): void
    {
        $finder = new ClassFinder();
        $classes = $finder->findClassNames([]);

        self::assertCount(0, $classes);
    }

    public function testBrokenSymlink(): void
    {
        $dir = sys_get_temp_dir() . '/class-finder-broken-symlink';

        if (!is_dir($dir)) {
            mkdir($dir);
        }

        symlink($dir . '/missing-target.php', $dir . '/broken.php');

        try {
            $finder = new ClassFinder();
            $classes = $finder->findClassNames([$dir]);

            self::assertCount(0, $classes);
        } finally {
            unlink($dir . '/broken.php');
            rmdir($dir);
        }
    }

    public function testLoadDirectory(): void
    {
        $finder = new ClassFinder();
        $classes = $finder->findClassNames([__DIR__ . '/../Fixture']);

        self::assertContains('Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile', $classes);
    }
}
