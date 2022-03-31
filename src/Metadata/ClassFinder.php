<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata;

use ReflectionClass;
use Symfony\Component\Finder\Finder;

use function count;
use function get_declared_classes;
use function in_array;

class ClassFinder
{
    /**
     * @param list<string> $paths
     *
     * @return list<class-string>
     */
    public function findClassNames(array $paths): array
    {
        $files = $this->findPhpFiles($paths);

        if (count($files) === 0) {
            return [];
        }

        return $this->getClassesInPhpFiles($files);
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function findPhpFiles(array $paths): array
    {
        if (count($paths) === 0) {
            return [];
        }

        $files = (new Finder())
            ->in($paths)
            ->files()
            ->name('*.php');

        if (!$files->hasResults()) {
            return [];
        }

        $result = [];

        foreach ($files as $file) {
            $path = $file->getRealPath();

            if (!$path) {
                continue;
            }

            $result[] = $path;
        }

        return $result;
    }

    /**
     * @param list<string> $files
     *
     * @return list<class-string>
     */
    private function getClassesInPhpFiles(array $files): array
    {
        foreach ($files as $file) {

            /** @psalm-suppress all */
            require_once $file;
        }

        $classes = get_declared_classes();
        $result = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $fileName = $reflection->getFileName();

            if ($fileName === false) {
                continue;
            }

            if (!in_array($fileName, $files, true)) {
                continue;
            }

            $result[] = $class;
        }

        return $result;
    }
}
