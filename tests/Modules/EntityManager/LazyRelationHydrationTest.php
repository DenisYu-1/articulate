<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Modules\EntityManager\Collection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\LazyCollection;
use Articulate\Modules\EntityManager\ObjectHydrator;
use Articulate\Modules\EntityManager\Proxy\ProxyInterface;
use Articulate\Modules\EntityManager\RelationshipLoader;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Schema\EntityMetadataRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

// ── Test entities ─────────────────────────────────────────────────────────────

#[Entity(tableName: 'lazy_hydration_posts')]
class LazyHydrationPost {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $title = null;
}

#[Entity(tableName: 'lazy_hydration_authors')]
class LazyHydrationAuthor {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $name = null;

    #[ManyToOne(targetEntity: LazyHydrationPost::class, lazy: true)]
    public ?LazyHydrationPost $featuredPost = null;

    #[OneToMany(targetEntity: LazyHydrationPost::class, ownedBy: 'author', lazy: true)]
    public ?Collection $posts = null;
}

#[Entity(tableName: 'lazy_hydration_profiles')]
class LazyHydrationProfile {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $bio = null;
}

#[Entity(tableName: 'lazy_hydration_users')]
class LazyHydrationUser {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $username = null;

    /** Owning side, lazy */
    #[OneToOne(targetEntity: LazyHydrationProfile::class, lazy: true)]
    public ?LazyHydrationProfile $profile = null;

    /** Inverse side OneToOne, lazy */
    #[OneToOne(targetEntity: LazyHydrationProfile::class, ownedBy: 'user', lazy: true)]
    public ?LazyHydrationProfile $inverseProfile = null;
}

#[Entity(tableName: 'lazy_hydration_tags')]
class LazyHydrationTag {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $label = null;
}

#[Entity(tableName: 'lazy_hydration_articles')]
class LazyHydrationArticle {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $headline = null;

    #[ManyToMany(targetEntity: LazyHydrationTag::class, lazy: true)]
    public ?Collection $tags = null;
}

// ── Eager counterparts (lazy: false, default) ─────────────────────────────────

#[Entity(tableName: 'eager_hydration_authors')]
class EagerHydrationAuthor {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $name = null;

    #[ManyToOne(targetEntity: LazyHydrationPost::class)]
    public ?LazyHydrationPost $featuredPost = null;

    #[OneToMany(targetEntity: LazyHydrationPost::class, ownedBy: 'author')]
    public ?Collection $posts = null;
}

// ── Test class ────────────────────────────────────────────────────────────────

class LazyRelationHydrationTest extends TestCase {
    private EntityMetadataRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new EntityMetadataRegistry();
    }

    /**
     * Builds an ObjectHydrator wired to a mock RelationshipLoader and EntityManager.
     *
     * @return array{ObjectHydrator, RelationshipLoader&\PHPUnit\Framework\MockObject\MockObject, EntityManager&\PHPUnit\Framework\MockObject\MockObject}
     */
    private function buildHydrator(): array
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);

        $em = $this->createMock(EntityManager::class);

        $loader = $this->createMock(RelationshipLoader::class);
        $loader->method('getMetadataRegistry')->willReturn($this->registry);
        $loader->method('getEntityManager')->willReturn($em);

        $hydrator = new ObjectHydrator($unitOfWork, $loader);

        return [$hydrator, $loader, $em];
    }

    // ─── ManyToOne lazy → proxy, no load() call ────────────────────────────────

    #[AllowMockObjectsWithoutExpectations]
    public function testLazyManyToOneCreatesProxyWithoutCallingLoad(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        // proxy must be an instance of the target entity class so PHP type check passes
        $proxy = $this->createStub(LazyHydrationPost::class);

        $loader->expects($this->never())->method('load');

        $em->expects($this->once())
            ->method('getReference')
            ->with(LazyHydrationPost::class, 42)
            ->willReturn($proxy);

        /** @var LazyHydrationAuthor $entity */
        $entity = $hydrator->hydrate(LazyHydrationAuthor::class, [
            'id'               => 1,
            'name'             => 'Alice',
            'featured_post_id' => 42,   // FK column name: snakeCase('featuredPost') + '_id'
        ]);

        $this->assertSame($proxy, $entity->featuredPost);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testLazyManyToOneWithNullFkLeavesPropertyNull(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $loader->expects($this->never())->method('load');
        $em->expects($this->never())->method('getReference');

        /** @var LazyHydrationAuthor $entity */
        $entity = $hydrator->hydrate(LazyHydrationAuthor::class, [
            'id'               => 1,
            'name'             => 'Alice',
            'featured_post_id' => null,
        ]);

        $this->assertNull($entity->featuredPost);
    }

    // ─── OneToMany lazy → LazyCollection, no load() call ─────────────────────

    #[AllowMockObjectsWithoutExpectations]
    public function testLazyOneToManyCreateLazyCollectionWithoutCallingLoad(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $loader->expects($this->never())->method('load');

        /** @var LazyHydrationAuthor $entity */
        $entity = $hydrator->hydrate(LazyHydrationAuthor::class, [
            'id'   => 1,
            'name' => 'Alice',
        ]);

        $this->assertInstanceOf(LazyCollection::class, $entity->posts);
        $this->assertFalse($entity->posts->isInitialized());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testLazyOneToManyCountUsesCountLoaderNotLoad(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $loader->expects($this->never())->method('load');
        $loader->expects($this->once())->method('count')->willReturn(3);

        /** @var LazyHydrationAuthor $entity */
        $entity = $hydrator->hydrate(LazyHydrationAuthor::class, [
            'id'   => 1,
            'name' => 'Alice',
        ]);

        $this->assertSame(3, $entity->posts->count());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testLazyOneToManyAddDoesNotTriggerLoad(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $loader->expects($this->never())->method('load');

        /** @var LazyHydrationAuthor $entity */
        $entity = $hydrator->hydrate(LazyHydrationAuthor::class, [
            'id'   => 1,
            'name' => 'Alice',
        ]);

        $newPost = new LazyHydrationPost();
        $newPost->id = 99;

        $entity->posts->add($newPost);

        $this->assertFalse($entity->posts->isInitialized());
        $this->assertTrue($entity->posts->isDirty());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testLazyOneToManyLoadTriggersOnIteration(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $post = new LazyHydrationPost();
        $post->id = 5;

        $loader->expects($this->once())
            ->method('load')
            ->willReturn([$post]);

        /** @var LazyHydrationAuthor $entity */
        $entity = $hydrator->hydrate(LazyHydrationAuthor::class, [
            'id'   => 1,
            'name' => 'Alice',
        ]);

        $items = $entity->posts->toArray();

        $this->assertCount(1, $items);
        $this->assertSame($post, $items[0]);
    }

    // ─── ManyToMany lazy → LazyCollection ────────────────────────────────────

    #[AllowMockObjectsWithoutExpectations]
    public function testLazyManyToManyCreatesLazyCollectionWithoutCallingLoad(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $loader->expects($this->never())->method('load');

        /** @var LazyHydrationArticle $entity */
        $entity = $hydrator->hydrate(LazyHydrationArticle::class, [
            'id'       => 1,
            'headline' => 'Breaking',
        ]);

        $this->assertInstanceOf(LazyCollection::class, $entity->tags);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testLazyManyToManyCountUsesCountLoader(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $loader->expects($this->never())->method('load');
        $loader->expects($this->once())->method('count')->willReturn(7);

        /** @var LazyHydrationArticle $entity */
        $entity = $hydrator->hydrate(LazyHydrationArticle::class, [
            'id'       => 1,
            'headline' => 'Breaking',
        ]);

        $this->assertSame(7, $entity->tags->count());
    }

    // ─── Owning OneToOne lazy → proxy ────────────────────────────────────────

    #[AllowMockObjectsWithoutExpectations]
    public function testLazyOwningSideOneToOneCreatesProxy(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        // proxy must extend the target entity class so PHP type check passes
        $proxy = $this->createStub(LazyHydrationProfile::class);

        $loader->expects($this->never())->method('load');
        $em->expects($this->once())
            ->method('getReference')
            ->with(LazyHydrationProfile::class, 10)
            ->willReturn($proxy);

        // inverseProfile (inverse lazy OneToOne) will also call createLazyReference; stub it
        $em->method('createLazyReference')
            ->willReturn($this->createStub(LazyHydrationProfile::class));

        /** @var LazyHydrationUser $entity */
        $entity = $hydrator->hydrate(LazyHydrationUser::class, [
            'id'         => 1,
            'username'   => 'bob',
            'profile_id' => 10,   // FK column name: snakeCase('profile') + '_id'
        ]);

        $this->assertSame($proxy, $entity->profile);
    }

    // ─── Inverse OneToOne lazy → custom proxy ────────────────────────────────

    #[AllowMockObjectsWithoutExpectations]
    public function testLazyInverseSideOneToOneCreatesProxyViaCreateLazyReference(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $proxy = $this->createStub(LazyHydrationProfile::class);

        $loader->expects($this->never())->method('load');
        $em->expects($this->once())
            ->method('createLazyReference')
            ->with(LazyHydrationProfile::class, $this->isInstanceOf(\Closure::class))
            ->willReturn($proxy);

        /** @var LazyHydrationUser $entity */
        $entity = $hydrator->hydrate(LazyHydrationUser::class, [
            'id'       => 1,
            'username' => 'bob',
        ]);

        $this->assertSame($proxy, $entity->inverseProfile);
    }

    // ─── Eager (non-lazy) relations keep existing behaviour ───────────────────

    #[AllowMockObjectsWithoutExpectations]
    public function testEagerManyToOneCallsLoadImmediately(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $post = new LazyHydrationPost();
        $post->id = 7;

        // Both relations (featuredPost ManyToOne, posts OneToMany) are eager on EagerHydrationAuthor.
        // Return type-appropriate values per relation so no TypeError occurs.
        $loader->expects($this->atLeastOnce())
            ->method('load')
            ->willReturnCallback(function (object $entity, $relation) use ($post) {
                if ($relation->isManyToOne()) {
                    return $post;
                }

                return []; // OneToMany returns empty array
            });

        /** @var EagerHydrationAuthor $entity */
        $entity = $hydrator->hydrate(EagerHydrationAuthor::class, [
            'id'   => 1,
            'name' => 'Eager author',
        ]);

        $this->assertNotNull($entity->featuredPost);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testEagerOneToManyCallsLoadAndWrapsInCollection(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $post = new LazyHydrationPost();
        $post->id = 7;

        // Both relations are eager; return type-appropriate values per relation.
        $loader->expects($this->atLeastOnce())
            ->method('load')
            ->willReturnCallback(function (object $entity, $relation) use ($post) {
                if ($relation->isOneToMany()) {
                    return [$post];
                }

                return null; // ManyToOne with no FK returns null
            });

        /** @var EagerHydrationAuthor $entity */
        $entity = $hydrator->hydrate(EagerHydrationAuthor::class, [
            'id'   => 1,
            'name' => 'Eager author',
        ]);

        $this->assertInstanceOf(Collection::class, $entity->posts);
        $this->assertNotInstanceOf(LazyCollection::class, $entity->posts);
        $this->assertSame(1, $entity->posts->count());
    }
}
