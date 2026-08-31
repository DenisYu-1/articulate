<?php

namespace Articulate\Modules\EntityManager;

use Closure;

/**
 * A pending in-memory #[Version] reconciliation for one entity, produced by
 * QueryExecutor once its UPDATE has passed the optimistic-lock check.
 *
 * EntityManager::flush() calls apply() before post-update callbacks so they see
 * the value the row now carries, then revert() from its catch block if the flush
 * never commits — so a mid-flush conflict can't leave the property ahead of the
 * row and poison every retry. Both operations write the captured original value
 * plus a fixed delta, so revert() is a safe no-op even when apply() never ran.
 */
final readonly class DeferredVersionBump {
    /**
     * @param Closure(int $delta): void $shiftFromOriginal sets every tracked #[Version]
     *        column to its pre-flush value plus $delta
     */
    public function __construct(private Closure $shiftFromOriginal)
    {
    }

    public function apply(): void
    {
        ($this->shiftFromOriginal)(1);
    }

    public function revert(): void
    {
        ($this->shiftFromOriginal)(0);
    }
}
