<?php

namespace Articulate\Tests\Modules\EntityManager;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToMany;
use Articulate\Attributes\Relations\OneToOne;
use Articulate\Connection;
use Articulate\Modules\EntityManager\Collection;
use Articulate\Modules\EntityManager\EntityManager;
use Articulate\Modules\EntityManager\LazyCollection;
use Articulate\Modules\EntityManager\ObjectHydrator;
use Articulate\Modules\EntityManager\RelationshipLoader;
use Articulate\Modules\EntityManager\UnitOfWork;
use Articulate\Schema\EntityMetadataRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// ── Test entities ─────────────────────────────────────────────────────────────

#[Entity(tableName: 'eager_override_comments')]
class EagerOverrideComment {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $body = null;
}

#[Entity(tableName: 'eager_override_profile')]
class EagerOverrideProfile {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $bio = null;
}

#[Entity(tableName: 'eager_override_tag')]
class EagerOverrideTag {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $name = null;
}

#[Entity(tableName: 'eager_override_posts')]
class EagerOverridePost {
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public ?string $title = null;

    /** lazy: true — normally deferred */
    #[ManyToOne(targetEntity: EagerOverrideComment::class, lazy: true)]
    public ?EagerOverrideComment $featured = null;

    /** lazy: true — normally deferred */
    #[OneToMany(targetEntity: EagerOverrideComment::class, ownedBy: 'post', lazy: true)]
    public ?Collection $comments = null;

    /** lazy: true owning OneToOne */
    #[OneToOne(targetEntity: EagerOverrideProfile::class, lazy: true)]
    public ?EagerOverrideProfile $profile = null;

    /** lazy: true — normally deferred */
    #[ManyToMany(targetEntity: EagerOverrideTag::class, lazy: true)]
    public ?Collection $tags = null;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * @return array{ObjectHydrator, RelationshipLoader&MockObject, EntityManager&MockObject}
 */
function buildEagerOverrideHydrator(EntityMetadataRegistry $registry): array
{
    $unitOfWork = (new TestCase())->createStub(UnitOfWork::class);
    $em = (new TestCase())->createMock(EntityManager::class);
    $loader = (new TestCase())->createMock(RelationshipLoader::class);

    $loader->method('getMetadataRegistry')->willReturn($registry);
    $loader->method('getEntityManager')->willReturn($em);

    return [new ObjectHydrator($unitOfWork, $loader), $loader, $em];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

class EagerLoadOverrideTest extends TestCase {
    private EntityMetadataRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new EntityMetadataRegistry();
    }

    private function buildHydrator(): array
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $em = $this->createMock(EntityManager::class);
        $loader = $this->createMock(RelationshipLoader::class);

        $loader->method('getMetadataRegistry')->willReturn($this->registry);
        $loader->method('getEntityManager')->willReturn($em);

        return [new ObjectHydrator($unitOfWork, $loader), $loader, $em];
    }

    // ── ManyToOne: $with override forces eager load ───────────────────────────

    #[AllowMockObjectsWithoutExpectations]
    public function testWithOverrideLazyManyToOneCallsLoadInsteadOfProxy(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $comment = new EagerOverrideComment();
        $comment->id = 7;

        $loader->expects($this->once())
            ->method('load')
            ->willReturn($comment);

        $em->expects($this->never())->method('getReference');

        /** @var EagerOverridePost $post */
        $post = $hydrator->hydrate(EagerOverridePost::class, [
            'id'           => 1,
            'title'        => 'Hello',
            'featured_id'  => 7,
        ], null, ['featured']);

        $this->assertSame($comment, $post->featured);
    }

    // ── OneToMany: $with override forces Collection (not LazyCollection) ──────

    #[AllowMockObjectsWithoutExpectations]
    public function testWithOverrideLazyOneToManyReturnsCollection(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $c1 = new EagerOverrideComment();
        $c1->id = 1;

        $loader->expects($this->once())
            ->method('load')
            ->willReturn([$c1]);

        /** @var EagerOverridePost $post */
        $post = $hydrator->hydrate(EagerOverridePost::class, ['id' => 1, 'title' => 'Hi'], null, ['comments']);

        $this->assertInstanceOf(Collection::class, $post->comments);
        $this->assertNotInstanceOf(LazyCollection::class, $post->comments);
        $this->assertCount(1, $post->comments);
    }

    // ── OneToOne owning: $with override forces eager load ────────────────────

    #[AllowMockObjectsWithoutExpectations]
    public function testWithOverrideLazyOwningOneToOneCallsLoad(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $profile = new EagerOverrideProfile();
        $profile->id = 5;

        $loader->expects($this->once())
            ->method('load')
            ->willReturn($profile);

        $em->expects($this->never())->method('getReference');
        $em->expects($this->never())->method('createLazyReference');

        /** @var EagerOverridePost $post */
        $post = $hydrator->hydrate(EagerOverridePost::class, ['id' => 1, 'title' => 'Hi'], null, ['profile']);

        $this->assertSame($profile, $post->profile);
    }

    // ── ManyToMany: $with override forces Collection (not LazyCollection) ─────

    #[AllowMockObjectsWithoutExpectations]
    public function testWithOverrideLazyManyToManyReturnsCollection(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $tag = new EagerOverrideTag();
        $tag->id = 3;

        $loader->expects($this->once())
            ->method('load')
            ->willReturn([$tag]);

        /** @var EagerOverridePost $post */
        $post = $hydrator->hydrate(EagerOverridePost::class, ['id' => 1, 'title' => 'Hi'], null, ['tags']);

        $this->assertInstanceOf(Collection::class, $post->tags);
        $this->assertNotInstanceOf(LazyCollection::class, $post->tags);
        $this->assertCount(1, $post->tags);
    }

    // ── Empty $with: all lazy relations stay lazy ─────────────────────────────

    #[AllowMockObjectsWithoutExpectations]
    public function testEmptyWithKeepsAllRelationsLazy(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $proxy = $this->createStub(EagerOverrideComment::class);

        $loader->expects($this->never())->method('load');
        $em->method('getReference')->willReturn($proxy);

        /** @var EagerOverridePost $post */
        $post = $hydrator->hydrate(EagerOverridePost::class, [
            'id'          => 1,
            'title'       => 'Hi',
            'featured_id' => 7,
        ], null, []);

        // ManyToOne stayed lazy → proxy via getReference
        $this->assertSame($proxy, $post->featured);
        // OneToMany stayed lazy → LazyCollection
        $this->assertInstanceOf(LazyCollection::class, $post->comments);
        // ManyToMany stayed lazy → LazyCollection
        $this->assertInstanceOf(LazyCollection::class, $post->tags);
    }

    // ── Relation not in $with stays lazy while others are forced eager ────────

    #[AllowMockObjectsWithoutExpectations]
    public function testOnlyNamedRelationsAreForceEager(): void
    {
        [$hydrator, $loader, $em] = $this->buildHydrator();

        $c1 = new EagerOverrideComment();
        $c1->id = 1;

        // load() called once — only for 'comments'; 'tags' stays lazy
        $loader->expects($this->once())
            ->method('load')
            ->willReturn([$c1]);

        /** @var EagerOverridePost $post */
        $post = $hydrator->hydrate(EagerOverridePost::class, ['id' => 1, 'title' => 'Hi'], null, ['comments']);

        $this->assertInstanceOf(Collection::class, $post->comments);
        $this->assertNotInstanceOf(LazyCollection::class, $post->comments);
        // tags not in $with → still lazy
        $this->assertInstanceOf(LazyCollection::class, $post->tags);
    }

    // ── EntityManager::findAll() rejects unknown relation names ──────────────

    public function testFindAllThrowsOnUnknownRelationInWith(): void
    {
        $em = new EntityManager($this->createStub(Connection::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/nonexistent/');

        $em->findAll(EagerOverridePost::class, with: ['nonexistent']);
    }

    // ── EntityManager::find() rejects unknown relation names ─────────────────

    public function testFindThrowsOnUnknownRelationInWith(): void
    {
        $em = new EntityManager($this->createStub(Connection::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/nonexistent/');

        $em->find(EagerOverridePost::class, 1, with: ['nonexistent']);
    }
}
