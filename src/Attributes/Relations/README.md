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
