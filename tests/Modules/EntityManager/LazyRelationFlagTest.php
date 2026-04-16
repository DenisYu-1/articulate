<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Reflection\RelationInterface;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\MorphMany;
use Articulate\Attributes\Relations\MorphOne;
use Articulate\Attributes\Relations\MorphTo;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Schema\EntityMetadataRegistry;
use PHPUnit\Framework\TestCase;

// ── Fixture entities ──────────────────────────────────────────────────────────

#[Entity(tableName: 'lazy_flag_targets')]
class LazyFlagTarget {
    #[PrimaryKey]
    public ?int $id = null;
}

#[Entity(tableName: 'lazy_flag_sources')]
class LazyFlagSource {
    #[PrimaryKey]
    public ?int $id = null;

    // lazy: false (default)
    #[ManyToOne(targetEntity: LazyFlagTarget::class)]
    public ?LazyFlagTarget $eagerM2o = null;

    // lazy: true
    #[ManyToOne(targetEntity: LazyFlagTarget::class, lazy: true)]
    public ?LazyFlagTarget $lazyM2o = null;

    // lazy: false (default)
    #[OneToMany(targetEntity: LazyFlagTarget::class, ownedBy: 'source')]
    public array $eagerO2m = [];

    // lazy: true
    #[OneToMany(targetEntity: LazyFlagTarget::class, ownedBy: 'source', lazy: true)]
    public array $lazyO2m = [];

    // lazy: false (default) — owning side
    #[OneToOne(targetEntity: LazyFlagTarget::class)]
    public ?LazyFlagTarget $eagerO2o = null;

    // lazy: true — owning side
    #[OneToOne(targetEntity: LazyFlagTarget::class, lazy: true)]
    public ?LazyFlagTarget $lazyO2o = null;

    // lazy: false (default)
    #[ManyToMany(targetEntity: LazyFlagTarget::class, referencedBy: 'sources')]
    public array $eagerM2m = [];

    // lazy: true
    #[ManyToMany(targetEntity: LazyFlagTarget::class, referencedBy: 'sources', lazy: true)]
    public array $lazyM2m = [];
}

// ── Test class ────────────────────────────────────────────────────────────────

class LazyRelationFlagTest extends TestCase {
    private EntityMetadataRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new EntityMetadataRegistry();
    }

    private function getRelation(string $propertyName): RelationInterface
    {
        $metadata = $this->registry->getMetadata(LazyFlagSource::class);
        $relation  = $metadata->getRelations()[$propertyName] ?? null;
        $this->assertInstanceOf(RelationInterface::class, $relation, "No relation '$propertyName' found");

        return $relation;
    }

    // ─── ManyToOne ────────────────────────────────────────────────────────────

    public function testManyToOneDefaultIsNotLazy(): void
    {
        $this->assertFalse($this->getRelation('eagerM2o')->isLazy());
    }

    public function testManyToOneExplicitLazyIsLazy(): void
    {
        $this->assertTrue($this->getRelation('lazyM2o')->isLazy());
    }

    // ─── OneToMany ────────────────────────────────────────────────────────────

    public function testOneToManyDefaultIsNotLazy(): void
    {
        $this->assertFalse($this->getRelation('eagerO2m')->isLazy());
    }

    public function testOneToManyExplicitLazyIsLazy(): void
    {
        $this->assertTrue($this->getRelation('lazyO2m')->isLazy());
    }

    // ─── OneToOne ─────────────────────────────────────────────────────────────

    public function testOneToOneDefaultIsNotLazy(): void
    {
        $this->assertFalse($this->getRelation('eagerO2o')->isLazy());
    }

    public function testOneToOneExplicitLazyIsLazy(): void
    {
        $this->assertTrue($this->getRelation('lazyO2o')->isLazy());
    }

    // ─── ManyToMany ───────────────────────────────────────────────────────────

    public function testManyToManyDefaultIsNotLazy(): void
    {
        $this->assertFalse($this->getRelation('eagerM2m')->isLazy());
    }

    public function testManyToManyExplicitLazyIsLazy(): void
    {
        $this->assertTrue($this->getRelation('lazyM2m')->isLazy());
    }

    // ─── Attribute constructors carry the lazy flag ───────────────────────────

    public function testManyToOneAttributeDefaultLazyIsFalse(): void
    {
        $attr = new ManyToOne();
        $this->assertFalse($attr->lazy);
    }

    public function testManyToOneAttributeExplicitLazyIsTrue(): void
    {
        $attr = new ManyToOne(lazy: true);
        $this->assertTrue($attr->lazy);
    }

    public function testOneToManyAttributeDefaultLazyIsFalse(): void
    {
        $attr = new OneToMany();
        $this->assertFalse($attr->lazy);
    }

    public function testOneToManyAttributeExplicitLazyIsTrue(): void
    {
        $attr = new OneToMany(lazy: true);
        $this->assertTrue($attr->lazy);
    }

    public function testOneToOneAttributeDefaultLazyIsFalse(): void
    {
        $attr = new OneToOne();
        $this->assertFalse($attr->lazy);
    }

    public function testOneToOneAttributeExplicitLazyIsTrue(): void
    {
        $attr = new OneToOne(lazy: true);
        $this->assertTrue($attr->lazy);
    }

    public function testManyToManyAttributeDefaultLazyIsFalse(): void
    {
        $attr = new ManyToMany();
        $this->assertFalse($attr->lazy);
    }

    public function testManyToManyAttributeExplicitLazyIsTrue(): void
    {
        $attr = new ManyToMany(lazy: true);
        $this->assertTrue($attr->lazy);
    }

    public function testMorphToAttributeDefaultLazyIsFalse(): void
    {
        $attr = new MorphTo();
        $this->assertFalse($attr->lazy);
    }

    public function testMorphToAttributeExplicitLazyIsTrue(): void
    {
        $attr = new MorphTo(lazy: true);
        $this->assertTrue($attr->lazy);
    }

    public function testMorphOneAttributeDefaultLazyIsFalse(): void
    {
        $attr = new MorphOne(targetEntity: LazyFlagTarget::class);
        $this->assertFalse($attr->lazy);
    }

    public function testMorphOneAttributeExplicitLazyIsTrue(): void
    {
        $attr = new MorphOne(targetEntity: LazyFlagTarget::class, lazy: true);
        $this->assertTrue($attr->lazy);
    }

    public function testMorphManyAttributeDefaultLazyIsFalse(): void
    {
        $attr = new MorphMany(targetEntity: LazyFlagTarget::class);
        $this->assertFalse($attr->lazy);
    }

    public function testMorphManyAttributeExplicitLazyIsTrue(): void
    {
        $attr = new MorphMany(targetEntity: LazyFlagTarget::class, lazy: true);
        $this->assertTrue($attr->lazy);
    }
}
