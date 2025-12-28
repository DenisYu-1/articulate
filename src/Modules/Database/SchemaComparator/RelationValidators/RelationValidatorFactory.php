<?php

namespace Articulate\Modules\Database\SchemaComparator\RelationValidators;

use Articulate\Attributes\Reflection\RelationInterface;

class RelationValidatorFactory {
    /**
     * @var RelationValidatorInterface[]
     */
    private array $validators;

    public function __construct()
    {
        $this->validators = [
            new OneToOneRelationValidator(),
            new ManyToOneRelationValidator(),
            new OneToManyRelationValidator(),
            new ManyToManyRelationValidator(),
            new PolymorphicRelationValidator(),
            new MorphToManyRelationValidator(),
        ];
    }

    public function getValidator(RelationInterface $relation): RelationValidatorInterface
    {
        foreach ($this->validators as $validator) {
            if ($validator->supports($relation)) {
                return $validator;
            }
        }

        throw new \RuntimeException('No validator found for relation type');
    }
}
