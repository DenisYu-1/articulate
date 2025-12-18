# One-to-One

- Owning side: `#[OneToOne(targetEntity: Foo::class|null, referencedBy: 'inverseProp', foreignKey: true|false)]` (no `ownedBy`). This side emits the column and optional FK.
- Inverse side: `#[OneToOne(ownedBy: 'owningProp')]` (foreign key is ignored/disabled on inverse). Emits no column/FK.
- Column name on owning side: snake_case(property) + `_id` by default; override with `column: 'custom_fk'`; nullable follows PHP type (`?Foo` → nullable); when multiple entities map the same table/column the nullable flag is relaxed if any entity allows nulls
- FK emitted only when `foreignKey=true`; references target table’s first primary key; when multiple entities map the same table/column the FK is kept if any relation requires it and targets must match
- Validation: inverse must exist, use `OneToOne`, not request FK, and `ownedBy` must match the owning property

- # Many-to-One / One-to-Many

- Owning side: `#[ManyToOne(targetEntity: Foo::class|null, referencedBy: 'inverseProp'|null, column: 'custom_fk'|null, nullable: bool|null, foreignKey: true|false)]`
- Inverse side: `#[OneToMany(ownedBy: 'owningProp', targetEntity: Foo::class|null)]` (no column/FK emitted on inverse)
- Column name on owning side: snake_case(property) + `_id` by default; override with `column`
- Nullability: follows `nullable` flag when set, otherwise PHP type allows null; when multiple entities map the same table/column the nullable flag is relaxed if any entity allows nulls
- FK emitted only on owning side when `foreignKey=true`; references target table’s first primary key; when multiple entities map the same table/column the FK is kept if any relation requires it, and target must be consistent
- Validation: if inverse is provided it must be `OneToMany` pointing back via `ownedBy`, must not request FK or be owning; inverse side must reference owning `ManyToOne` via `ownedBy`
- Example: `#[ManyToOne(referencedBy: 'articles')] public User $author;` + `#[OneToMany(ownedBy: 'author')] public Article $articles;` creates `author_id` on `Article` with optional FK to `user.id`
- Example diff: turning off `foreignKey` drops only the FK constraint, leaving the column intact

# One-to-Many specifics

- Inverse-only: emits no columns or FKs; `ownedBy` is required and must reference a `ManyToOne` on the target pointing back.
- Type: property should be an iterable/array collection; non-collection types are rejected.
- Inverse must not declare `foreignKey`/ownership.

- # Many-to-Many

- Owning side: `#[ManyToMany(targetEntity: Foo::class, referencedBy: 'inverseProp', mappingTable: new MappingTable(name: 'custom', properties: [new MappingTableProperty('created_at', 'datetime', true)]))]`
- Inverse side: `#[ManyToMany(ownedBy: 'owningProp', targetEntity: Foo::class)]` (no mapping properties on inverse)
- Default mapping table name: snake_case of both table names sorted; defaults to composite PK on both join columns
- Join columns: `owner_table_id` + `target_table_id`; FKs reference the first primary key of each side
- Extra mapping columns come from `MappingTableProperty`; collection access via `MappingCollection/MappingItem`
- Mapping table name can be reused across owning relations with the same join columns; extra properties are merged (union) and any shared column becomes nullable if at least one relation marks it nullable
- Conflicting definitions for the same extra column (type/length/default) raise validation errors
- Validation: only one owning side (no `ownedBy`), inverse must point back via `ownedBy`; name mismatches or inverse missing property throw

# Polymorphic Relations

Polymorphic relations allow a model to belong to more than one type of model using a single association. This is useful for scenarios like comments, polls, or tags that can be attached to multiple different entity types.

## MorphTo (Polymorphic Belongs-To)

The inverse side of a polymorphic relation. Creates two columns: `{property}_type` (VARCHAR) and `{property}_id` (INT). Unlike the old design, MorphTo is now open-ended and doesn't need to know about all possible target entities.

```php
#[Entity]
class Poll
{
    #[Property]
    public int $id;

    #[Property(maxLength: 255)]
    public string $question;

    // Can morph to ANY entity type - no hardcoded list needed!
    #[MorphTo]
    public $pollable;
}
```

**Parameters:**
- `typeColumn`: Custom column name for the type field (default: `{property}_type`)
- `idColumn`: Custom column name for the ID field (default: `{property}_id`)

**Automatic Features:**
- Column name resolution based on property name using snake_case conversion
- Composite index generation for `(type_column, id_column)` for optimal query performance
- Comprehensive validation of relation configuration

### Morph Type Registry (Optional Optimization)

For better performance and cleaner database storage, you can register short aliases for entity class names:

```php
use Articulate\Attributes\Relations\MorphTypeRegistry;

// Register aliases for cleaner morph_type values
MorphTypeRegistry::register(Post::class, 'post');
MorphTypeRegistry::register(Comment::class, 'comment');
MorphTypeRegistry::register(Article::class, 'article');

// Now morph_type will store 'post', 'comment', 'article' instead of full class names
```

**Benefits:**
- Shorter database values (better performance, smaller indexes)
- Cleaner data exports
- Backward compatible - works with existing data

## MorphOne (Polymorphic Has-One)

One-to-one polymorphic relation. The owning side.

```php
#[Entity]
class Post
{
    #[Property]
    public int $id;

    #[Property(maxLength: 255)]
    public string $title;

    // Creates a relation to Poll where morph_type = Post::class
    #[MorphOne(targetEntity: Poll::class, referencedBy: 'morphable')]
    public Poll $poll;
}
```

## MorphMany (Polymorphic Has-Many)

One-to-many polymorphic relation. The owning side.

```php
#[Entity]
class Post
{
    #[Property]
    public int $id;

    #[Property(maxLength: 255)]
    public string $title;

    // Creates a relation to multiple Polls where morph_type = Post::class
    #[MorphMany(targetEntity: Poll::class, referencedBy: 'morphable')]
    public array $polls;
}
```

**Parameters:**
- `targetEntity`: The entity class this relation morphs to
- `morphType`: The morph type identifier (defaults to the target entity class name)
- `typeColumn`: Custom column name for the type field (default: `{property}_type`)
- `idColumn`: Custom column name for the ID field (default: `{property}_id`)
- `referencedBy`: The property name on the target entity that references back
- `foreignKey`: Whether to create foreign key constraints (default: true, but not recommended for polymorphic)

## Database Schema

Polymorphic relations create two columns in the database:

```sql
CREATE TABLE poll (
  id INT PRIMARY KEY,
  question VARCHAR(255),
  morphable_type VARCHAR(255) NOT NULL,  -- Stores the entity class name
  morphable_id INT NOT NULL             -- Stores the entity ID
);
```

## Usage Example

```php
// Post entity
#[Entity]
class Post
{
    #[Property]
    public int $id;

    #[Property(maxLength: 255)]
    public string $title;

    #[MorphMany(targetEntity: Poll::class, referencedBy: 'morphable')]
    public array $polls;
}

// Comment entity
#[Entity]
class Comment
{
    #[Property]
    public int $id;

    #[Property(maxLength: 500)]
    public string $content;

    #[MorphMany(targetEntity: Poll::class, referencedBy: 'morphable')]
    public array $polls;
}

// Poll entity (polymorphic) - doesn't reference any specific entities!
#[Entity]
class Poll
{
    #[Property]
    public int $id;

    #[Property(maxLength: 255)]
    public string $question;

    // Can morph to ANY entity - completely open-ended
    // Automatically creates: pollable_type VARCHAR(255), pollable_id INT
    // Automatically creates: INDEX idx_pollable_type_pollable_id (pollable_type, pollable_id)
    #[MorphTo]
    public $pollable;
}
```

**Adding New Pollable Entities:**

You can add new pollable entities without modifying the `Poll` entity:

```php
// Later, you can add Article entity without modifying Poll!
#[Entity]
class Article
{
    #[Property]
    public int $id;

    #[Property(maxLength: 255)]
    public string $title;

    // Creates polls where morph_type = Article::class
    #[MorphMany(targetEntity: Poll::class, referencedBy: 'pollable')]
    public array $polls;
}
```

## Migration Generation

Polymorphic relations automatically generate the appropriate database schema:

```sql
-- Table creation
CREATE TABLE poll (
  id INT PRIMARY KEY,
  question VARCHAR(255) NOT NULL,
  pollable_type VARCHAR(255) NOT NULL,    -- Stores entity class name
  pollable_id INT NOT NULL,               -- Stores entity ID
  INDEX idx_pollable_type_pollable_id (pollable_type, pollable_id)
);

-- Adding to existing table
ALTER TABLE poll ADD COLUMN pollable_type VARCHAR(255) NOT NULL;
ALTER TABLE poll ADD COLUMN pollable_id INT NOT NULL;
ALTER TABLE poll ADD INDEX idx_pollable_type_pollable_id (pollable_type, pollable_id);
```

**Migration Rollback:**
```sql
ALTER TABLE poll DROP INDEX idx_pollable_type_pollable_id;
ALTER TABLE poll DROP COLUMN pollable_id;
ALTER TABLE poll DROP COLUMN pollable_type;
```

## Important Notes

1. **Open-Ended Design**: The `Poll` entity doesn't need to know about all possible pollable entities. New entities can be added without modifying existing code.

2. **No Foreign Keys**: Polymorphic relations don't create traditional foreign key constraints since they can reference multiple tables.

3. **Type Safety**: Runtime type resolution is used - the morph type column stores the actual entity class name.

4. **Performance**: Composite indexes on `(morph_type, morph_id)` enable efficient queries. For cross-entity queries, consider UNION operations.

5. **Validation**: The system validates that inverse relations properly reference back to the owning entities.

6. **Migration Support**: Full migration generation and rollback support included automatically.
