<?php

namespace Articulate\Modules\EntityManager;

class IdentityMap {
    /** @var array<class-string, array<string, object>> */
    private array $entities = [];

    public function add(object $entity, mixed $id, ?string $classOverride = null): void
    {
        $className = $classOverride ?? $entity::class;
        $key = $this->generateKey($id);

        if (!isset($this->entities[$className])) {
            $this->entities[$className] = [];
        }

        $this->entities[$className][$key] = $entity;
    }

    public function get(string $class, mixed $id): ?object
    {
        $key = $this->generateKey($id);

        return $this->entities[$class][$key] ?? null;
    }

    public function has(string $class, mixed $id): bool
    {
        $key = $this->generateKey($id);

        return isset($this->entities[$class][$key]);
    }

    public function remove(object $entity): void
    {
        $className = $entity::class;

        if ($this->removeFromClass($className, $entity)) {
            return;
        }

        foreach (array_keys($this->entities) as $class) {
            if ($class !== $className && $this->removeFromClass($class, $entity)) {
                return;
            }
        }
    }

    private function removeFromClass(string $className, object $entity): bool
    {
        if (!isset($this->entities[$className])) {
            return false;
        }

        foreach ($this->entities[$className] as $key => $storedEntity) {
            if ($storedEntity === $entity) {
                unset($this->entities[$className][$key]);

                return true;
            }
        }

        return false;
    }

    public function clear(?string $class = null): void
    {
        if ($class === null) {
            $this->entities = [];
        } else {
            unset($this->entities[$class]);
        }
    }

    public function generateKey(mixed $id): string
    {
        if (is_array($id)) {
            // Composite key support
            ksort($id); // Ensure consistent ordering

            return json_encode($id);
        }

        return (string) $id;
    }
}
