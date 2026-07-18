<?php

namespace Articulate\Commands;

use Articulate\Attributes\Reflection\ReflectionEntity;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class EntityClassDiscovery {
    private const array DEFAULT_PATHS = ['src/Entities', 'src/Entity'];

    /**
     * @param array<int, string>|null $entitiesPath
     * @return list<ReflectionEntity>
     */
    public function discover(?array $entitiesPath): array
    {
        $entities = [];
        foreach ($this->resolveEntitiesDirs($entitiesPath) as $entitiesDir) {
            foreach ($this->discoverInDirectory($entitiesDir) as $entity) {
                $entities[$entity->getName()] = $entity;
            }
        }

        return array_values($entities);
    }

    /**
     * @return list<ReflectionEntity>
     */
    private function discoverInDirectory(string $entitiesDir): array
    {
        $classNames = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($entitiesDir));

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $realPath = $file->getRealPath();
            if ($realPath === false || !str_starts_with($realPath, $entitiesDir . DIRECTORY_SEPARATOR)) {
                continue;
            }
            $contents = file_get_contents($realPath);
            if ($contents === false) {
                continue;
            }
            if (preg_match('/namespace\s+(.+?);/', $contents, $namespaceMatches) &&
                preg_match('/class\s+(\w+)/', $contents, $classMatches)) {
                $classNames[] = $namespaceMatches[1] . '\\' . $classMatches[1];
            }
        }

        return array_values(array_filter(
            array_map(fn (string $className) => new ReflectionEntity($className), $classNames),
            fn (ReflectionEntity $entity) => $entity->isEntity()
        ));
    }

    /**
     * @param array<int, string>|null $entitiesPath
     * @return list<string>
     */
    private function resolveEntitiesDirs(?array $entitiesPath): array
    {
        if ($entitiesPath !== null) {
            if (empty($entitiesPath)) {
                throw new \RuntimeException('At least one entities path must be provided.');
            }

            return array_map(fn (string $path) => $this->resolveDir($path), $entitiesPath);
        }

        foreach (self::DEFAULT_PATHS as $path) {
            $resolved = realpath($path);
            if ($resolved !== false) {
                return [$resolved];
            }
        }

        throw new \RuntimeException('Entities directory is not found. Expected one of: ' . implode(', ', self::DEFAULT_PATHS) . ', or set a custom path.');
    }

    private function resolveDir(string $path): string
    {
        $resolved = realpath($path);
        if ($resolved === false) {
            throw new \RuntimeException(sprintf('Entities directory not found at configured path: %s', $path));
        }

        return $resolved;
    }
}
