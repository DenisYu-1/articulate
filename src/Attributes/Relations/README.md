# One-to-One

- Main (owning) side: `#[OneToOne(targetEntity: Foo::class|null, inversedBy: 'inverseProp', foreignKey: true|false, mainSide: true)]`
- Inverse side: `#[OneToOne(mappedBy: 'mainProp')]` (must NOT set `mainSide` or `foreignKey`)
- Column name on main side: snake_case(property) + `_id` by default; override with `column: 'custom_fk'`; nullable follows PHP type (`?Foo` → nullable)
- FK emitted only when `foreignKey=true`; references target table’s first primary key
- Validation: inverse must exist, use `OneToOne`, not be `mainSide`, not request FK, and `mappedBy` must match the main property

# Many-to-One / One-to-Many

- Owning side: `#[ManyToOne(targetEntity: Foo::class|null, inversedBy: 'inverseProp'|null, column: 'custom_fk'|null, nullable: bool|null, foreignKey: true|false)]`
- Inverse side: `#[OneToMany(mappedBy: 'owningProp', targetEntity: Foo::class|null)]` (no column/FK emitted on inverse)
- Column name on owning side: snake_case(property) + `_id` by default; override with `column`
- Nullability: follows `nullable` flag when set, otherwise PHP type allows null
- FK emitted only on owning side when `foreignKey=true`; references target table’s first primary key
- Validation: if inverse is provided it must be `OneToMany` pointing back via `mappedBy`, must not request FK or be owning; inverse side must reference owning `ManyToOne` via `mappedBy`
- Example: `#[ManyToOne(inversedBy: 'articles')] public User $author;` + `#[OneToMany(mappedBy: 'author')] public Article $articles;` creates `author_id` on `Article` with optional FK to `user.id`
- Example diff: turning off `foreignKey` drops only the FK constraint, leaving the column intact

# One-to-Many specifics

- Inverse-only: emits no columns or FKs; `mappedBy` is required and must reference a `ManyToOne` on the target pointing back.
- Type: property should be an iterable/array collection; non-collection types are rejected.
- Inverse must not declare `foreignKey`/ownership.

# Many-to-Many

- Owning side: `#[ManyToMany(targetEntity: Foo::class, inversedBy: 'inverseProp', mappingTable: new MappingTable(name: 'custom', properties: [new MappingTableProperty('created_at', 'datetime', true)]))]`
- Inverse side: `#[ManyToMany(mappedBy: 'owningProp', targetEntity: Foo::class)]` (no mapping properties on inverse)
- Default mapping table name: snake_case of both table names sorted; defaults to composite PK on both join columns
- Join columns: `owner_table_id` + `target_table_id`; FKs reference the first primary key of each side
- Extra mapping columns come from `MappingTableProperty`; collection access via `MappingCollection/MappingItem`
- Mapping table name can be reused across owning relations with the same join columns; extra properties are merged (union) and any shared column becomes nullable if at least one relation marks it nullable
- Conflicting definitions for the same extra column (type/length/default) raise validation errors
- Validation: only one owning side (no `mappedBy`), inverse must point back via `mappedBy`; name mismatches or inverse missing property throw
