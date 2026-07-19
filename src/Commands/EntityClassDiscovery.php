<?php

namespace Articulate\Commands;

use Articulate\Attributes\Reflection\ReflectionEntity;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class EntityClassDiscovery {
    private const array DEFAULT_PATHS = ['src/Entities', 'src/Entity'];

    private const array NAME_TOKEN_TYPES = [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];

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
            foreach ($this->extractClassNames($contents) as $className) {
                $classNames[] = $className;
            }
        }

        $entities = [];
        foreach ($classNames as $className) {
            if (!class_exists($className)) {
                continue;
            }
            $entity = new ReflectionEntity($className);
            if ($entity->isEntity()) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * @return list<string>
     */
    private function extractClassNames(string $contents): array
    {
        $namespace = '';
        $classNames = [];
        $tokens = token_get_all($contents);

        foreach ($tokens as $i => $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->readNamespaceName($tokens, $i + 1);

                continue;
            }

            if ($token[0] === T_CLASS && !$this->isAnonymousClass($tokens, $i)) {
                $name = $this->nextNameToken($tokens, $i + 1);
                if ($name !== null) {
                    $classNames[] = ltrim($namespace . '\\' . $name, '\\');
                }
            }
        }

        return $classNames;
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private function readNamespaceName(array $tokens, int $start): string
    {
        $name = '';
        for ($i = $start; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if ($token === ';' || $token === '{') {
                break;
            }
            if (is_array($token) && in_array($token[0], self::NAME_TOKEN_TYPES, true)) {
                $name .= $token[1];
            }
        }

        return $name;
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private function nextNameToken(array $tokens, int $start): ?string
    {
        for ($i = $start; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }
            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }

            return null;
        }

        return null;
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private function isAnonymousClass(array $tokens, int $classTokenIndex): bool
    {
        return $this->nextNameToken($tokens, $classTokenIndex + 1) === null;
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
