<?php

namespace Articulate\Tests\Modules\DatabaseSchemaComparator\TestEntities;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Property;
use Articulate\Attributes\Relations\OneToMany;

#[Entity]
class TestManyToOneTargetMappedByMismatch
{
    #[Property]
    public int $id;

    #[OneToMany(mappedBy: 'otherProperty', targetEntity: TestManyToOneOwnerMappedByMismatch::class)]
    public TestManyToOneOwnerMappedByMismatch $owners;
}

