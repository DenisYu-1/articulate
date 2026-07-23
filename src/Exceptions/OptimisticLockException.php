<?php

namespace Articulate\Exceptions;

/**
 * Thrown when a #[Version]-checked UPDATE (or soft-delete UPDATE) affects
 * zero rows. This means either the version column no longer matches the
 * value the UPDATE expected (a concurrent writer changed the row first) or
 * the row itself has been deleted — like Doctrine's OptimisticLockException,
 * the two cases are not distinguished, since telling them apart would need
 * an extra SELECT this exception is meant to avoid.
 */
class OptimisticLockException extends ArticulateException {
}
