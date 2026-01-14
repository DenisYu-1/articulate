<?php

namespace Articulate\Tests\Attributes\Relations;

use Articulate\Attributes\Relations\ManyToMany;
use Articulate\Attributes\Relations\MappingTable;
use Articulate\Attributes\Relations\MorphedByMany;
use Articulate\Attributes\Relations\MorphMany;
use Articulate\Attributes\Relations\MorphOne;
use Articulate\Attributes\Relations\MorphTo;
use Articulate\Attributes\Relations\MorphToMany;
use Articulate\Attributes\Relations\PolymorphicColumnResolution;
use Attribute;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class RelationAttributesTest extends TestCase {
    public function testManyToManyAttributeDefaultConstructor(): void
    {
        $manyToMany = new ManyToMany();

        $this->assertNull($manyToMany->targetEntity);
        $this->assertNull($manyToMany->ownedBy);
        $this->assertNull($manyToMany->referencedBy);
        $this->assertNull($manyToMany->mappingTable);
    }

    public function testManyToManyAttributeWithTargetEntity(): void
    {
        $manyToMany = new ManyToMany(targetEntity: 'App\\Entity\\User');

        $this->assertEquals('App\\Entity\\User', $manyToMany->targetEntity);
        $this->assertNull($manyToMany->ownedBy);
        $this->assertNull($manyToMany->referencedBy);
        $this->assertNull($manyToMany->mappingTable);
    }

    public function testManyToManyAttributeWithOwnedBy(): void
    {
        $manyToMany = new ManyToMany(ownedBy: 'users');

        $this->assertNull($manyToMany->targetEntity);
        $this->assertEquals('users', $manyToMany->ownedBy);
        $this->assertNull($manyToMany->referencedBy);
        $this->assertNull($manyToMany->mappingTable);
    }

    public function testManyToManyAttributeWithReferencedBy(): void
    {
        $manyToMany = new ManyToMany(referencedBy: 'posts');

        $this->assertNull($manyToMany->targetEntity);
        $this->assertNull($manyToMany->ownedBy);
        $this->assertEquals('posts', $manyToMany->referencedBy);
        $this->assertNull($manyToMany->mappingTable);
    }

    public function testManyToManyAttributeWithMappingTable(): void
    {
        $mappingTable = new MappingTable('user_posts');
        $manyToMany = new ManyToMany(mappingTable: $mappingTable);

        $this->assertNull($manyToMany->targetEntity);
        $this->assertNull($manyToMany->ownedBy);
        $this->assertNull($manyToMany->referencedBy);
        $this->assertSame($mappingTable, $manyToMany->mappingTable);
    }

    public function testManyToManyAttributeWithAllParameters(): void
    {
        $mappingTable = new MappingTable('user_posts');
        $manyToMany = new ManyToMany(
            targetEntity: 'App\\Entity\\User',
            ownedBy: 'users',
            referencedBy: 'posts',
            mappingTable: $mappingTable
        );

        $this->assertEquals('App\\Entity\\User', $manyToMany->targetEntity);
        $this->assertEquals('users', $manyToMany->ownedBy);
        $this->assertEquals('posts', $manyToMany->referencedBy);
        $this->assertSame($mappingTable, $manyToMany->mappingTable);
    }

    public function testManyToManyGetTargetEntity(): void
    {
        $manyToMany = new ManyToMany(targetEntity: 'App\\Entity\\User');

        $this->assertEquals('App\\Entity\\User', $manyToMany->getTargetEntity());
    }

    public function testManyToManyGetTargetEntityNull(): void
    {
        $manyToMany = new ManyToMany();

        $this->assertNull($manyToMany->getTargetEntity());
    }

    public function testManyToManyGetColumnReturnsNull(): void
    {
        $manyToMany = new ManyToMany();

        $this->assertNull($manyToMany->getColumn());
    }

    public function testManyToManyAttributeIsAttribute(): void
    {
        $reflection = new ReflectionClass(ManyToMany::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        $this->assertEquals(Attribute::TARGET_PROPERTY, $attribute->flags);
    }

    // MorphMany tests
    public function testMorphManyAttributeDefaultConstructor(): void
    {
        $morphMany = new MorphMany('App\\Entity\\User');

        $this->assertEquals('App\\Entity\\User', $morphMany->targetEntity);
        $this->assertNull($morphMany->morphType);
        $this->assertNull($morphMany->typeColumn);
        $this->assertNull($morphMany->idColumn);
        $this->assertNull($morphMany->referencedBy);
        $this->assertTrue($morphMany->foreignKey);
    }

    public function testMorphManyAttributeWithAllParameters(): void
    {
        $morphMany = new MorphMany(
            targetEntity: 'App\\Entity\\Post',
            morphType: 'commentable',
            typeColumn: 'commentable_type',
            idColumn: 'commentable_id',
            referencedBy: 'comments',
            foreignKey: false
        );

        $this->assertEquals('App\\Entity\\Post', $morphMany->targetEntity);
        $this->assertEquals('commentable', $morphMany->morphType);
        $this->assertEquals('commentable_type', $morphMany->typeColumn);
        $this->assertEquals('commentable_id', $morphMany->idColumn);
        $this->assertEquals('comments', $morphMany->referencedBy);
        $this->assertFalse($morphMany->foreignKey);
    }

    public function testMorphManyGetTargetEntity(): void
    {
        $morphMany = new MorphMany('App\\Entity\\User');

        $this->assertEquals('App\\Entity\\User', $morphMany->getTargetEntity());
    }

    public function testMorphManyGetColumn(): void
    {
        $morphMany = new MorphMany('App\\Entity\\User', idColumn: 'user_id');

        $this->assertEquals('user_id', $morphMany->getColumn());
    }

    public function testMorphManyGetMorphTypeDefault(): void
    {
        $morphMany = new MorphMany('App\\Entity\\User');

        $this->assertEquals('App\\Entity\\User', $morphMany->getMorphType());
    }

    public function testMorphManyGetMorphTypeCustom(): void
    {
        $morphMany = new MorphMany('App\\Entity\\User', morphType: 'person');

        $this->assertEquals('person', $morphMany->getMorphType());
    }

    public function testMorphManyAttributeIsAttribute(): void
    {
        $reflection = new ReflectionClass(MorphMany::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        $this->assertEquals(Attribute::TARGET_PROPERTY, $attribute->flags);
    }

    // MorphOne tests
    public function testMorphOneAttributeDefaultConstructor(): void
    {
        $morphOne = new MorphOne('App\\Entity\\User');

        $this->assertEquals('App\\Entity\\User', $morphOne->targetEntity);
        $this->assertNull($morphOne->morphType);
        $this->assertNull($morphOne->typeColumn);
        $this->assertNull($morphOne->idColumn);
        $this->assertNull($morphOne->referencedBy);
        $this->assertTrue($morphOne->foreignKey);
    }

    public function testMorphOneAttributeWithAllParameters(): void
    {
        $morphOne = new MorphOne(
            targetEntity: 'App\\Entity\\Post',
            morphType: 'imageable',
            typeColumn: 'imageable_type',
            idColumn: 'imageable_id',
            referencedBy: 'image',
            foreignKey: false
        );

        $this->assertEquals('App\\Entity\\Post', $morphOne->targetEntity);
        $this->assertEquals('imageable', $morphOne->morphType);
        $this->assertEquals('imageable_type', $morphOne->typeColumn);
        $this->assertEquals('imageable_id', $morphOne->idColumn);
        $this->assertEquals('image', $morphOne->referencedBy);
        $this->assertFalse($morphOne->foreignKey);
    }

    public function testMorphOneGetTargetEntity(): void
    {
        $morphOne = new MorphOne('App\\Entity\\User');

        $this->assertEquals('App\\Entity\\User', $morphOne->getTargetEntity());
    }

    public function testMorphOneGetColumn(): void
    {
        $morphOne = new MorphOne('App\\Entity\\User', idColumn: 'user_id');

        $this->assertEquals('user_id', $morphOne->getColumn());
    }

    public function testMorphOneGetMorphTypeDefault(): void
    {
        $morphOne = new MorphOne('App\\Entity\\User');

        $this->assertEquals('App\\Entity\\User', $morphOne->getMorphType());
    }

    public function testMorphOneGetMorphTypeCustom(): void
    {
        $morphOne = new MorphOne('App\\Entity\\User', morphType: 'person');

        $this->assertEquals('person', $morphOne->getMorphType());
    }

    public function testMorphOneAttributeIsAttribute(): void
    {
        $reflection = new ReflectionClass(MorphOne::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        $this->assertEquals(Attribute::TARGET_PROPERTY, $attribute->flags);
    }

    // MorphTo tests
    public function testMorphToAttributeDefaultConstructor(): void
    {
        $morphTo = new MorphTo();

        $this->assertNull($morphTo->typeColumn);
        $this->assertNull($morphTo->idColumn);
    }

    public function testMorphToAttributeWithParameters(): void
    {
        $morphTo = new MorphTo(
            typeColumn: 'morphable_type',
            idColumn: 'morphable_id'
        );

        $this->assertEquals('morphable_type', $morphTo->typeColumn);
        $this->assertEquals('morphable_id', $morphTo->idColumn);
    }

    public function testMorphToGetTargetEntityReturnsNull(): void
    {
        $morphTo = new MorphTo();

        $this->assertNull($morphTo->getTargetEntity());
    }

    public function testMorphToGetColumn(): void
    {
        $morphTo = new MorphTo(idColumn: 'user_id');

        $this->assertEquals('user_id', $morphTo->getColumn());
    }

    public function testMorphToGetRecommendedIndexName(): void
    {
        $morphTo = new MorphTo(
            typeColumn: 'commentable_type',
            idColumn: 'commentable_id'
        );

        $this->assertEquals('idx_commentable_type_commentable_id', $morphTo->getRecommendedIndexName());
    }

    public function testMorphToGetRecommendedIndexColumns(): void
    {
        $morphTo = new MorphTo(
            typeColumn: 'commentable_type',
            idColumn: 'commentable_id'
        );

        $this->assertEquals(['commentable_type', 'commentable_id'], $morphTo->getRecommendedIndexColumns());
    }

    public function testMorphToAttributeIsAttribute(): void
    {
        $reflection = new ReflectionClass(MorphTo::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        $this->assertEquals(Attribute::TARGET_PROPERTY, $attribute->flags);
    }

    // MorphToMany tests
    public function testMorphToManyAttributeDefaultConstructor(): void
    {
        $morphToMany = new MorphToMany('App\\Entity\\Tag', 'taggable');

        $this->assertEquals('App\\Entity\\Tag', $morphToMany->targetEntity);
        $this->assertEquals('taggable', $morphToMany->name);
        $this->assertNull($morphToMany->typeColumn);
        $this->assertNull($morphToMany->idColumn);
        $this->assertNull($morphToMany->targetIdColumn);
        $this->assertNull($morphToMany->mappingTable);
        $this->assertTrue($morphToMany->foreignKey);
    }

    public function testMorphToManyAttributeWithAllParameters(): void
    {
        $mappingTable = new MappingTable('tag_mappings');
        $morphToMany = new MorphToMany(
            targetEntity: 'App\\Entity\\Tag',
            name: 'taggable',
            typeColumn: 'taggable_type',
            idColumn: 'taggable_id',
            targetIdColumn: 'tag_id',
            mappingTable: $mappingTable,
            foreignKey: false
        );

        $this->assertEquals('App\\Entity\\Tag', $morphToMany->targetEntity);
        $this->assertEquals('taggable', $morphToMany->name);
        $this->assertEquals('taggable_type', $morphToMany->typeColumn);
        $this->assertEquals('taggable_id', $morphToMany->idColumn);
        $this->assertEquals('tag_id', $morphToMany->targetIdColumn);
        $this->assertSame($mappingTable, $morphToMany->mappingTable);
        $this->assertFalse($morphToMany->foreignKey);
    }

    public function testMorphToManyGetTargetEntity(): void
    {
        $morphToMany = new MorphToMany('App\\Entity\\Tag', 'taggable');

        $this->assertEquals('App\\Entity\\Tag', $morphToMany->getTargetEntity());
    }

    public function testMorphToManyGetColumn(): void
    {
        $morphToMany = new MorphToMany('App\\Entity\\Tag', 'taggable', idColumn: 'taggable_id');

        $this->assertEquals('taggable_id', $morphToMany->getColumn());
    }

    public function testMorphToManyGetMorphName(): void
    {
        $morphToMany = new MorphToMany('App\\Entity\\Tag', 'taggable');

        $this->assertEquals('taggable', $morphToMany->getMorphName());
    }

    public function testMorphToManyGetTypeColumnDefault(): void
    {
        $morphToMany = new MorphToMany('App\\Entity\\Tag', 'taggable');

        $this->assertEquals('taggable_type', $morphToMany->getTypeColumn());
    }

    public function testMorphToManyGetTypeColumnCustom(): void
    {
        $morphToMany = new MorphToMany('App\\Entity\\Tag', 'taggable', typeColumn: 'morph_type');

        $this->assertEquals('morph_type', $morphToMany->getTypeColumn());
    }

    public function testMorphToManyGetIdColumnDefault(): void
    {
        $morphToMany = new MorphToMany('App\\Entity\\Tag', 'taggable');

        $this->assertEquals('taggable_id', $morphToMany->getIdColumn());
    }

    public function testMorphToManyGetIdColumnCustom(): void
    {
        $morphToMany = new MorphToMany('App\\Entity\\Tag', 'taggable', idColumn: 'morph_id');

        $this->assertEquals('morph_id', $morphToMany->getIdColumn());
    }

    public function testMorphToManyGetTargetIdColumnUnresolved(): void
    {
        $morphToMany = new MorphToMany('App\\Entity\\Tag', 'taggable');

        $this->assertEquals('__UNRESOLVED_TARGET_ID__', $morphToMany->getTargetIdColumn());
    }

    public function testMorphToManyGetTargetIdColumnCustom(): void
    {
        $morphToMany = new MorphToMany('App\\Entity\\Tag', 'taggable', targetIdColumn: 'tag_id');

        $this->assertEquals('tag_id', $morphToMany->getTargetIdColumn());
    }

    public function testMorphToManyResolveColumnNames(): void
    {
        $morphToMany = new MorphToMany('App\\Entity\\Tag', 'taggable');

        $morphToMany->resolveColumnNames('tags', 'tags');

        $this->assertEquals('taggable_type', $morphToMany->getTypeColumn());
        $this->assertEquals('taggable_id', $morphToMany->getIdColumn());
        $this->assertEquals('tags_id', $morphToMany->getTargetIdColumn());
    }

    public function testMorphToManyGetDefaultMappingTableName(): void
    {
        $morphToMany = new MorphToMany('App\\Entity\\Tag', 'taggable');

        $this->assertEquals('taggables', $morphToMany->getDefaultMappingTableName());
    }

    public function testMorphToManyAttributeIsAttribute(): void
    {
        $reflection = new ReflectionClass(MorphToMany::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        $this->assertEquals(Attribute::TARGET_PROPERTY, $attribute->flags);
    }

    // MorphedByMany tests
    public function testMorphedByManyAttributeDefaultConstructor(): void
    {
        $morphedByMany = new MorphedByMany('App\\Entity\\Post', 'taggable');

        $this->assertEquals('App\\Entity\\Post', $morphedByMany->targetEntity);
        $this->assertEquals('taggable', $morphedByMany->name);
        $this->assertNull($morphedByMany->typeColumn);
        $this->assertNull($morphedByMany->idColumn);
        $this->assertNull($morphedByMany->targetIdColumn);
        $this->assertNull($morphedByMany->mappingTable);
        $this->assertTrue($morphedByMany->foreignKey);
    }

    public function testMorphedByManyAttributeWithAllParameters(): void
    {
        $mappingTable = new MappingTable('tag_mappings');
        $morphedByMany = new MorphedByMany(
            targetEntity: 'App\\Entity\\Post',
            name: 'taggable',
            typeColumn: 'taggable_type',
            idColumn: 'taggable_id',
            targetIdColumn: 'post_id',
            mappingTable: $mappingTable,
            foreignKey: false
        );

        $this->assertEquals('App\\Entity\\Post', $morphedByMany->targetEntity);
        $this->assertEquals('taggable', $morphedByMany->name);
        $this->assertEquals('taggable_type', $morphedByMany->typeColumn);
        $this->assertEquals('taggable_id', $morphedByMany->idColumn);
        $this->assertEquals('post_id', $morphedByMany->targetIdColumn);
        $this->assertSame($mappingTable, $morphedByMany->mappingTable);
        $this->assertFalse($morphedByMany->foreignKey);
    }

    public function testMorphedByManyGetTargetEntity(): void
    {
        $morphedByMany = new MorphedByMany('App\\Entity\\Post', 'taggable');

        $this->assertEquals('App\\Entity\\Post', $morphedByMany->getTargetEntity());
    }

    public function testMorphedByManyGetColumn(): void
    {
        $morphedByMany = new MorphedByMany('App\\Entity\\Post', 'taggable', idColumn: 'taggable_id');

        $this->assertEquals('taggable_id', $morphedByMany->getColumn());
    }

    public function testMorphedByManyGetMorphName(): void
    {
        $morphedByMany = new MorphedByMany('App\\Entity\\Post', 'taggable');

        $this->assertEquals('taggable', $morphedByMany->getMorphName());
    }

    public function testMorphedByManyGetTypeColumnDefault(): void
    {
        $morphedByMany = new MorphedByMany('App\\Entity\\Post', 'taggable');

        $this->assertEquals('taggable_type', $morphedByMany->getTypeColumn());
    }

    public function testMorphedByManyGetTypeColumnCustom(): void
    {
        $morphedByMany = new MorphedByMany('App\\Entity\\Post', 'taggable', typeColumn: 'morph_type');

        $this->assertEquals('morph_type', $morphedByMany->getTypeColumn());
    }

    public function testMorphedByManyGetIdColumnDefault(): void
    {
        $morphedByMany = new MorphedByMany('App\\Entity\\Post', 'taggable');

        $this->assertEquals('taggable_id', $morphedByMany->getIdColumn());
    }

    public function testMorphedByManyGetIdColumnCustom(): void
    {
        $morphedByMany = new MorphedByMany('App\\Entity\\Post', 'taggable', idColumn: 'morph_id');

        $this->assertEquals('morph_id', $morphedByMany->getIdColumn());
    }

    public function testMorphedByManyGetTargetIdColumnUnresolved(): void
    {
        $morphedByMany = new MorphedByMany('App\\Entity\\Post', 'taggable');

        $this->assertEquals('__UNRESOLVED_TARGET_ID__', $morphedByMany->getTargetIdColumn());
    }

    public function testMorphedByManyGetTargetIdColumnCustom(): void
    {
        $morphedByMany = new MorphedByMany('App\\Entity\\Post', 'taggable', targetIdColumn: 'post_id');

        $this->assertEquals('post_id', $morphedByMany->getTargetIdColumn());
    }

    public function testMorphedByManyResolveColumnNames(): void
    {
        $morphedByMany = new MorphedByMany('App\\Entity\\Post', 'taggable');

        // This method exists but doesn't do much for MorphedByMany
        $morphedByMany->resolveColumnNames('posts', 'posts');

        $this->assertTrue(method_exists($morphedByMany, 'resolveColumnNames'));
    }

    public function testMorphedByManyGetDefaultMappingTableName(): void
    {
        $morphedByMany = new MorphedByMany('App\\Entity\\Post', 'taggable');

        $this->assertEquals('taggables', $morphedByMany->getDefaultMappingTableName());
    }

    public function testMorphedByManyAttributeIsAttribute(): void
    {
        $reflection = new ReflectionClass(MorphedByMany::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        $this->assertEquals(Attribute::TARGET_PROPERTY, $attribute->flags);
    }

    // PolymorphicColumnResolution trait tests
    public function testPolymorphicColumnResolutionTrait(): void
    {
        // Create a mock class that uses the trait
        $mockClass = new class() {
            use PolymorphicColumnResolution;

            public ?string $typeColumn = null;

            public ?string $idColumn = null;
        };

        // Test default column resolution
        $mockClass->resolveColumnNames('commentable');

        $this->assertEquals('commentable_type', $mockClass->getTypeColumn());
        $this->assertEquals('commentable_id', $mockClass->getIdColumn());
    }

    public function testPolymorphicColumnResolutionWithCustomColumns(): void
    {
        $mockClass = new class() {
            use PolymorphicColumnResolution;

            public ?string $typeColumn = 'morph_type';

            public ?string $idColumn = 'morph_id';
        };

        $mockClass->resolveColumnNames('commentable');

        $this->assertEquals('morph_type', $mockClass->getTypeColumn());
        $this->assertEquals('morph_id', $mockClass->getIdColumn());
    }

    public function testPolymorphicColumnResolutionUnresolved(): void
    {
        $mockClass = new class() {
            use PolymorphicColumnResolution;

            public ?string $typeColumn = null;

            public ?string $idColumn = null;
        };

        // Without calling resolveColumnNames
        $this->assertEquals('__UNRESOLVED_TYPE__', $mockClass->getTypeColumn());
        $this->assertEquals('__UNRESOLVED_ID__', $mockClass->getIdColumn());
    }

    public function testPolymorphicColumnResolutionConvertToSnakeCase(): void
    {
        $mockClass = new class() {
            use PolymorphicColumnResolution;

            public function convertToSnakeCasePublic(string $string): string
            {
                return $this->convertToSnakeCase($string);
            }
        };

        $this->assertEquals('user_name', $mockClass->convertToSnakeCasePublic('userName'));
        $this->assertEquals('post_comment', $mockClass->convertToSnakeCasePublic('postComment'));
        $this->assertEquals('simple', $mockClass->convertToSnakeCasePublic('simple'));
        $this->assertEquals('a_b_c', $mockClass->convertToSnakeCasePublic('ABC'));
    }
}
