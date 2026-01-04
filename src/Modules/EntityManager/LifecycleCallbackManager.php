<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Attributes\Lifecycle\PostLoad;
use Articulate\Attributes\Lifecycle\PostPersist;
use Articulate\Attributes\Lifecycle\PostRemove;
use Articulate\Attributes\Lifecycle\PostUpdate;
use Articulate\Attributes\Lifecycle\PrePersist;
use Articulate\Attributes\Lifecycle\PreRemove;
use Articulate\Attributes\Lifecycle\PreUpdate;

/**
 * Manages lifecycle callbacks for entities.
 */
class LifecycleCallbackManager {
    private array $callbacks = [];

    /**
     * Get all callback methods for an entity class and callback type.
     *
     * @param string $entityClass
     * @param string $callbackType One of: prePersist, postPersist, preUpdate, postUpdate, preRemove, postRemove, postLoad
     * @return array List of callable methods
     */
    public function getCallbacks(string $entityClass, string $callbackType): array
    {
        if (!isset($this->callbacks[$entityClass])) {
            $this->loadCallbacks($entityClass);
        }

        return $this->callbacks[$entityClass][$callbackType] ?? [];
    }

    /**
     * Invoke all callbacks of a specific type for an entity.
     *
     * @param object $entity
     * @param string $callbackType
     */
    public function invokeCallbacks(object $entity, string $callbackType): void
    {
        $callbacks = $this->getCallbacks($entity::class, $callbackType);

        foreach ($callbacks as $callback) {
            $entity->{$callback}();
        }
    }

    /**
     * Load callback methods for an entity class.
     */
    private function loadCallbacks(string $entityClass): void
    {
        $reflection = new \ReflectionClass($entityClass);
        $callbacks = [
            'prePersist' => [],
            'postPersist' => [],
            'preUpdate' => [],
            'postUpdate' => [],
            'preRemove' => [],
            'postRemove' => [],
            'postLoad' => [],
        ];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes();

            foreach ($attributes as $attribute) {
                $attributeName = $attribute->getName();

                switch ($attributeName) {
                    case PrePersist::class:
                        $callbacks['prePersist'][] = $method->getName();

                        break;
                    case PostPersist::class:
                        $callbacks['postPersist'][] = $method->getName();

                        break;
                    case PreUpdate::class:
                        $callbacks['preUpdate'][] = $method->getName();

                        break;
                    case PostUpdate::class:
                        $callbacks['postUpdate'][] = $method->getName();

                        break;
                    case PreRemove::class:
                        $callbacks['preRemove'][] = $method->getName();

                        break;
                    case PostRemove::class:
                        $callbacks['postRemove'][] = $method->getName();

                        break;
                    case PostLoad::class:
                        $callbacks['postLoad'][] = $method->getName();

                        break;
                }
            }
        }

        $this->callbacks[$entityClass] = $callbacks;
    }
}
