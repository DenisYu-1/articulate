<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Modules\EntityManager\Collection;
use Articulate\Modules\EntityManager\LazyCollection;
use PHPUnit\Framework\TestCase;

class LazyCollectionItem {
    public function __construct(public int $id, public string $name = '') {}
}

class LazyCollectionTest extends TestCase {
    // ── loader invocation tracking ────────────────────────────────────────────

    public function testLoaderIsNotCalledOnConstruction(): void
    {
        $called = false;
        new LazyCollection(function () use (&$called) {
            $called = true;

            return [];
        });

        $this->assertFalse($called);
    }

    public function testLoaderIsCalledOnFirstIteration(): void
    {
        $item  = new LazyCollectionItem(1);
        $calls = 0;

        $col = new LazyCollection(function () use ($item, &$calls) {
            $calls++;

            return [$item];
        });

        foreach ($col as $el) {
            $this->assertSame($item, $el);
        }

        $this->assertSame(1, $calls);
    }

    public function testLoaderIsCalledOnlyOnce(): void
    {
        $calls = 0;
        $col   = new LazyCollection(function () use (&$calls) {
            $calls++;

            return [];
        });

        $col->toArray();
        $col->toArray();
        $col->first();

        $this->assertSame(1, $calls);
    }

    // ── count: uses countLoader before initialization ─────────────────────────

    public function testCountUsesCountLoaderWithoutFullLoad(): void
    {
        $loaderCalled = false;
        $col = new LazyCollection(
            function () use (&$loaderCalled) {
                $loaderCalled = true;

                return [];
            },
            fn () => 7,
        );

        $this->assertSame(7, $col->count());
        $this->assertFalse($loaderCalled, 'full loader must not fire on count()');
    }

    public function testCountFallsBackToLoaderWhenNoCountLoader(): void
    {
        $item = new LazyCollectionItem(1);
        $col  = new LazyCollection(fn () => [$item]);

        $this->assertSame(1, $col->count());
        $this->assertTrue($col->isInitialized());
    }

    public function testCountAfterInitializationUsesItemCount(): void
    {
        $dbCount = 3;
        $col     = new LazyCollection(
            fn () => [new LazyCollectionItem(1), new LazyCollectionItem(2)],
            fn () => $dbCount, // would return 3, but after init real count wins
        );

        $col->toArray(); // force initialization
        $this->assertSame(2, $col->count()); // items loaded, not DB count
    }

    // ── isEmpty / isNotEmpty ───────────────────────────────────────────────────

    public function testIsEmptyUsesCountLoaderWithoutFullLoad(): void
    {
        $loaderCalled = false;
        $col = new LazyCollection(
            function () use (&$loaderCalled) {
                $loaderCalled = true;

                return [];
            },
            fn () => 0,
        );

        $this->assertTrue($col->isEmpty());
        $this->assertFalse($loaderCalled);
    }

    public function testIsNotEmptyUsesCountLoaderWithoutFullLoad(): void
    {
        $loaderCalled = false;
        $col = new LazyCollection(
            function () use (&$loaderCalled) {
                $loaderCalled = true;

                return [new LazyCollectionItem(1)];
            },
            fn () => 1,
        );

        $this->assertTrue($col->isNotEmpty());
        $this->assertFalse($loaderCalled);
    }

    // ── add without full load ─────────────────────────────────────────────────

    public function testAddBeforeInitializationDoesNotTriggerLoader(): void
    {
        $loaderCalled = false;
        $col = new LazyCollection(function () use (&$loaderCalled) {
            $loaderCalled = true;

            return [];
        });

        $col->add(new LazyCollectionItem(99));

        $this->assertFalse($loaderCalled);
        $this->assertTrue($col->isDirty());
        $this->assertFalse($col->isInitialized());
    }

    public function testAddBeforeInitializationIsIncludedAfterLoad(): void
    {
        $existing = new LazyCollectionItem(1);
        $pending  = new LazyCollectionItem(2);

        $col = new LazyCollection(fn () => [$existing]);
        $col->add($pending);

        $items = $col->toArray();

        $this->assertCount(2, $items);
        $this->assertSame($existing, $items[0]);
        $this->assertSame($pending, $items[1]);
    }

    public function testCountReflectsPendingAdditionsBeforeInit(): void
    {
        $col = new LazyCollection(fn () => [], fn () => 5);
        $col->add(new LazyCollectionItem(1));
        $col->add(new LazyCollectionItem(2));

        $this->assertSame(7, $col->count()); // 5 from DB + 2 pending
    }

    public function testMultipleAdditionsBufferedBeforeInit(): void
    {
        $a = new LazyCollectionItem(10);
        $b = new LazyCollectionItem(20);

        $col = new LazyCollection(fn () => []);
        $col->add($a)->add($b);

        $this->assertSame([$a, $b], $col->toArray());
    }

    // ── remove without full load ──────────────────────────────────────────────

    public function testRemoveBeforeInitializationDoesNotTriggerLoader(): void
    {
        $loaderCalled = false;
        $item = new LazyCollectionItem(5);

        $col = new LazyCollection(function () use ($item, &$loaderCalled) {
            $loaderCalled = true;

            return [$item];
        });

        $col->remove($item);

        $this->assertFalse($loaderCalled);
        $this->assertTrue($col->isDirty());
    }

    public function testRemoveBeforeInitializationIsAppliedAfterLoad(): void
    {
        $keep   = new LazyCollectionItem(1);
        $delete = new LazyCollectionItem(2);

        $col = new LazyCollection(fn () => [$keep, $delete]);
        $col->remove($delete);

        $items = $col->toArray();

        $this->assertCount(1, $items);
        $this->assertSame($keep, $items[0]);
    }

    public function testCountReflectsPendingRemovalsBeforeInit(): void
    {
        $col = new LazyCollection(fn () => [], fn () => 5);
        $col->remove(new LazyCollectionItem(1)); // pending removal

        $this->assertSame(4, $col->count()); // 5 - 1
    }

    // ── append via array syntax (null offset) ────────────────────────────────

    public function testOffsetSetNullBuffersBeforeInit(): void
    {
        $loaderCalled = false;
        $item         = new LazyCollectionItem(1);

        $col = new LazyCollection(function () use (&$loaderCalled) {
            $loaderCalled = true;

            return [];
        });

        $col[] = $item;

        $this->assertFalse($loaderCalled);
        $this->assertSame([$item], $col->toArray());
    }

    // ── methods that trigger initialization ───────────────────────────────────

    public function testContainsTriggersInit(): void
    {
        $item = new LazyCollectionItem(1);
        $col  = new LazyCollection(fn () => [$item]);

        $this->assertTrue($col->contains($item));
        $this->assertTrue($col->isInitialized());
    }

    public function testFirstTriggersInit(): void
    {
        $item = new LazyCollectionItem(42);
        $col  = new LazyCollection(fn () => [$item]);

        $this->assertSame($item, $col->first());
    }

    public function testLastTriggersInit(): void
    {
        $a   = new LazyCollectionItem(1);
        $b   = new LazyCollectionItem(2);
        $col = new LazyCollection(fn () => [$a, $b]);

        $this->assertSame($b, $col->last());
    }

    public function testMapTriggersInit(): void
    {
        $col = new LazyCollection(fn () => [new LazyCollectionItem(7)]);

        $result = $col->map(fn (LazyCollectionItem $i) => $i->id);

        $this->assertSame([7], $result);
    }

    public function testFilterReturnsLazyCollectionWithMatchingItems(): void
    {
        $a   = new LazyCollectionItem(1);
        $b   = new LazyCollectionItem(2);
        $col = new LazyCollection(fn () => [$a, $b]);

        $filtered = $col->filter(fn (LazyCollectionItem $i) => $i->id === 1);

        $this->assertInstanceOf(LazyCollection::class, $filtered);
        $this->assertSame(1, $filtered->count());
        $this->assertSame($a, $filtered->first());
    }

    public function testOffsetExistsTriggersInit(): void
    {
        $col = new LazyCollection(fn () => [new LazyCollectionItem(1)]);

        $this->assertTrue(isset($col[0]));
    }

    public function testOffsetGetTriggersInit(): void
    {
        $item = new LazyCollectionItem(3);
        $col  = new LazyCollection(fn () => [$item]);

        $this->assertSame($item, $col[0]);
    }

    public function testIterationTriggersInit(): void
    {
        $items = [new LazyCollectionItem(1), new LazyCollectionItem(2)];
        $col   = new LazyCollection(fn () => $items);

        $collected = [];
        foreach ($col as $item) {
            $collected[] = $item->id;
        }

        $this->assertSame([1, 2], $collected);
    }

    // ── extends Collection ───────────────────────────────────────────────────

    public function testIsInstanceOfCollection(): void
    {
        $this->assertInstanceOf(Collection::class, new LazyCollection(fn () => []));
    }

    public function testInitializedStateIsFalse(): void
    {
        $col = new LazyCollection(fn () => []);
        $this->assertFalse($col->isInitialized());
    }

    public function testInitializedStateIsTrueAfterLoad(): void
    {
        $col = new LazyCollection(fn () => []);
        $col->toArray();
        $this->assertTrue($col->isInitialized());
    }

    public function testIsNotDirtyInitially(): void
    {
        $col = new LazyCollection(fn () => []);
        $this->assertFalse($col->isDirty());
    }
}
