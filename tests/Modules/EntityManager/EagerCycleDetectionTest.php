<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Modules\EntityManager\Collection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\LazyCollection;
use Articulate\Modules\EntityManager\ObjectHydrator;
use Articulate\Modules\EntityManager\RelationshipLoader;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Schema\EntityMetadataRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

// ── Mutually eager ManyToMany entities ────────────────────────────────────────

#[Entity(tableName: 'cycle_alpha')]
class CycleAlpha {
    #[PrimaryKey]
    public ?int $id = null;

    /** @var Collection<CycleBeta>|null */
    #[ManyToMany(targetEntity: CycleBeta::class, lazy: false)]
    public ?Collection $betas = null;
}

#[Entity(tableName: 'cycle_beta')]
class CycleBeta {
    #[PrimaryKey]
    public ?int $id = null;

    /** @var Collection<CycleAlpha>|null */
    #[ManyToMany(targetEntity: CycleAlpha::class, lazy: false)]
    public ?Collection $alphas = null;
}

// ── Test ──────────────────────────────────────────────────────────────────────

class EagerCycleDetectionTest extends TestCase {
    private EntityMetadataRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new EntityMetadataRegistry();
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testMutualEagerManyToManyDoesNotOverflowStack(): void
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $em         = $this->createStub(EntityManager::class);
        $loader     = $this->createMock(RelationshipLoader::class);

        $loader->method('getMetadataRegistry')->willReturn($this->registry);
        $loader->method('getEntityManager')->willReturn($em);

        $hydrator = new ObjectHydrator($unitOfWork, $loader);

        // When loading CycleAlpha's betas, simulate hydrating a CycleBeta.
        // That CycleBeta will itself try to eagerly load alphas (back to CycleAlpha).
        // The cycle guard must break the recursion and fall back to LazyCollection.
        $loader->method('load')
            ->willReturnCallback(static function (object $entity) use ($hydrator): array {
                if ($entity instanceof CycleAlpha) {
                    $beta     = $hydrator->hydrate(CycleBeta::class, ['id' => 10]);
                    return [$beta];
                }
                // Should never be reached eagerly for CycleBeta → cycle guard blocks it.
                return [];
            });

        $loader->method('count')->willReturn(0);

        /** @var CycleAlpha $alpha */
        $alpha = $hydrator->hydrate(CycleAlpha::class, ['id' => 1]);

        // Alpha's betas loaded eagerly as a Collection.
        $this->assertInstanceOf(Collection::class, $alpha->betas);
        $this->assertNotInstanceOf(LazyCollection::class, $alpha->betas);
        $this->assertCount(1, $alpha->betas);

        /** @var CycleBeta $beta */
        $beta = $alpha->betas->first();
        $this->assertInstanceOf(CycleBeta::class, $beta);

        // Beta's back-reference to alphas must be lazy (cycle guard intercepted it).
        $this->assertInstanceOf(LazyCollection::class, $beta->alphas);
    }
}
