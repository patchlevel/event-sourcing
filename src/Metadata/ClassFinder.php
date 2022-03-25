<?php

namespace Patchlevel\EventSourcing\Metadata;

use ReflectionClass;

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
     * @param class-string $attributeClass
     *
     * @return list<class-string>
     */
    public function findClassNamesByAttribute(array $paths, string $attributeClass)
    {
        $classes = $this->findClassNames($paths);

        $result = [];

        foreach ($classes as $class) {
            $reflection = $this->reflectionClass($class);

            if (count($reflection->getAttributes($attributeClass)) === 0) {
                continue;
            }

            $result[] = $class;
        }

        return $result;
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
            $reflection = $this->reflectionClass($class);
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

    /**
     * @param class-string $class
     * @return ReflectionClass
     */
    private function reflectionClass(string $class): ReflectionClass
    {
        return new ReflectionClass($class);
    }
}