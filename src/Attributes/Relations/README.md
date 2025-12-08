# One-to-One

- Main (owning) side: `#[OneToOne(targetEntity: Foo::class|null, inversedBy: 'inverseProp', foreignKey: true|false, mainSide: true)]`
- Inverse side: `#[OneToOne(mappedBy: 'mainProp')]` (must NOT set `mainSide` or `foreignKey`)
- Column name on main side: snake_case(property) + `_id` by default; override with `column: 'custom_fk'`; nullable follows PHP type (`?Foo` → nullable)
- FK emitted only when `foreignKey=true`; references target table’s first primary key
- Validation: inverse must exist, use `OneToOne`, not be `mainSide`, not request FK, and `mappedBy` must match the main property
