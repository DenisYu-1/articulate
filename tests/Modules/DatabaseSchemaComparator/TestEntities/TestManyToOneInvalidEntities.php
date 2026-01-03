<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\ManyToOne;
use Articulate\Attributes\Relations\OneToMany;

#[Entity]
class TestManyToOneTargetMissingInverse {
    #[Property]
    public int $id;
}

#[Entity]
class TestManyToOneOwnerMissingInverse {
    #[Property]
    public int $id;

    #[ManyToOne(referencedBy: 'missingProperty')]
    public TestManyToOneTargetMissingInverse $target;
}

#[Entity]
class TestManyToOneTargetMappedByMismatch {
    #[Property]
    public int $id;

    #[OneToMany(ownedBy: 'otherProperty', targetEntity: TestManyToOneOwnerMappedByMismatch::class)]
    public TestManyToOneOwnerMappedByMismatch $owners;
}

#[Entity]
class TestManyToOneOwnerMappedByMismatch {
    #[Property]
    public int $id;

    #[ManyToOne(referencedBy: 'owners')]
    public TestManyToOneTargetMappedByMismatch $targetMismatch;
}

#[Entity]
class TestManyToOneTargetInverseIsOwning {
    #[Property]
    public int $id;

    #[ManyToOne]
    public TestManyToOneOwnerInverseIsOwning $owners;
}

#[Entity]
class TestManyToOneOwnerInverseIsOwning {
    #[Property]
    public int $id;

    #[ManyToOne(referencedBy: 'owners')]
    public TestManyToOneTargetInverseIsOwning $target;
}
