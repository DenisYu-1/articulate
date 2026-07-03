<?php

namespace Articulate\Modules\EntityManager;

use Articulate\Schema\EntityMetadataRegistry;
use InvalidArgumentException;

class UnitOfWorkRegistry {
    /** @var UnitOfWork[] */
    private array $unitOfWorks = [];

    private UnitOfWork $activeUnitOfWork;

    private bool $changeTrackingStrategyPrototypeConsumed = false;

    public function __construct(
        private readonly ChangeTrackingStrategy $changeTrackingStrategy,
        private readonly bool $usesDefaultChangeTrackingStrategy,
        private readonly LifecycleCallbackManager $callbackManager,
        private readonly EntityMetadataRegistry $metadataRegistry,
    ) {
        $initialUow = new UnitOfWork($this->createChangeTrackingStrategy(), $this->callbackManager, $this->metadataRegistry);
        $this->unitOfWorks[] = $initialUow;
        $this->activeUnitOfWork = $initialUow;
    }

    /**
     * @return UnitOfWork[]
     */
    public function all(): array
    {
        return $this->unitOfWorks;
    }

    public function active(): UnitOfWork
    {
        return $this->activeUnitOfWork;
    }

    public function create(): UnitOfWork
    {
        $unitOfWork = new UnitOfWork($this->createChangeTrackingStrategy(), $this->callbackManager, $this->metadataRegistry);
        $this->unitOfWorks[] = $unitOfWork;

        return $unitOfWork;
    }

    public function setActive(UnitOfWork $unitOfWork): void
    {
        if (!in_array($unitOfWork, $this->unitOfWorks, strict: true)) {
            throw new InvalidArgumentException('UnitOfWork does not belong to this EntityManager.');
        }

        $this->activeUnitOfWork = $unitOfWork;
    }

    public function remove(UnitOfWork $unitOfWork): void
    {
        if (!in_array($unitOfWork, $this->unitOfWorks, strict: true)) {
            throw new InvalidArgumentException('UnitOfWork does not belong to this EntityManager.');
        }

        if ($unitOfWork === $this->activeUnitOfWork) {
            throw new InvalidArgumentException('Cannot remove the active UnitOfWork. Set a different active UnitOfWork first.');
        }

        $unitOfWork->clear();
        $this->unitOfWorks = array_values(
            array_filter($this->unitOfWorks, fn ($uow) => $uow !== $unitOfWork)
        );
    }

    public function clearAll(): void
    {
        foreach ($this->unitOfWorks as $unitOfWork) {
            $unitOfWork->clear();
        }
    }

    public function detachFromAll(object $entity): void
    {
        foreach ($this->unitOfWorks as $unitOfWork) {
            $unitOfWork->detach($entity);
        }
    }

    public function getEntityState(object $entity): EntityState
    {
        $hasDetachedState = false;
        $hasRemovedState = false;

        foreach ($this->unitOfWorks as $unitOfWork) {
            $state = $unitOfWork->getEntityState($entity);

            if ($state === EntityState::MANAGED) {
                return EntityState::MANAGED;
            }

            if ($state === EntityState::REMOVED) {
                $hasRemovedState = true;
            }

            if ($state === EntityState::DETACHED) {
                $hasDetachedState = true;
            }
        }

        if ($hasRemovedState) {
            return EntityState::REMOVED;
        }

        if ($hasDetachedState) {
            return EntityState::DETACHED;
        }

        return EntityState::NEW;
    }

    private function createChangeTrackingStrategy(): ChangeTrackingStrategy
    {
        if ($this->usesDefaultChangeTrackingStrategy) {
            return new DeferredImplicitStrategy($this->metadataRegistry);
        }

        $reflection = new \ReflectionObject($this->changeTrackingStrategy);
        if ($reflection->isCloneable()) {
            return clone $this->changeTrackingStrategy;
        }

        if (!$this->changeTrackingStrategyPrototypeConsumed) {
            $this->changeTrackingStrategyPrototypeConsumed = true;

            return $this->changeTrackingStrategy;
        }

        throw new \LogicException('Custom ChangeTrackingStrategy must be cloneable to create an independent UnitOfWork.');
    }
}
