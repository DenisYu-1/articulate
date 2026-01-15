<?php

namespace Articulate\Modules\Database\SchemaComparator;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionManyToMany;
use Articulate\Attributes\Reflection\ReflectionMorphToMany;
use Articulate\Attributes\Relations\MappingTableProperty;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\RelationValidatorFactory;
use RuntimeException;

class RelationDefinitionCollector {
    public function __construct(
        private readonly RelationValidatorFactory $relationValidatorFactory,
    ) {
    }

    /**
     * @param ReflectionEntity[] $entities
     */
    public function validateRelations(array $entities): void
    {
        foreach ($entities as $entity) {
            foreach ($entity->getEntityRelationProperties() as $relation) {
                $validator = $this->relationValidatorFactory->getValidator($relation);
                $validator->validate($relation);
            }
        }
    }

    /**
     * @param ReflectionEntity[] $entities
     * @return array<string, array{
     *     tableName: string,
     *     ownerTable: string,
     *     targetTable: string,
     *     ownerJoinColumn: string,
     *     targetJoinColumn: string,
     *     ownerReferencedColumn: string,
     *     targetReferencedColumn: string,
     *     extraProperties: MappingTableProperty[],
     *     primaryColumns: string[]
     * }>
     */
    public function collectManyToManyTables(array $entities): array
    {
        $definitions = [];
        foreach ($entities as $entity) {
            foreach ($entity->getEntityRelationProperties() as $relation) {
                if (!$relation instanceof ReflectionManyToMany) {
                    continue;
                }
                if (!$relation->isOwningSide()) {
                    continue;
                }
                $ownerEntity = new ReflectionEntity($relation->getDeclaringClassName());
                $targetEntity = new ReflectionEntity($relation->getTargetEntity());
                $tableName = $relation->getTableName();

                if (!isset($definitions[$tableName])) {
                    $definitions[$tableName] = [
                        'tableName' => $tableName,
                        'ownerTable' => $ownerEntity->getTableName(),
                        'targetTable' => $targetEntity->getTableName(),
                        'ownerJoinColumn' => $relation->getOwnerJoinColumn(),
                        'targetJoinColumn' => $relation->getTargetJoinColumn(),
                        'ownerReferencedColumn' => $relation->getOwnerPrimaryColumn(),
                        'targetReferencedColumn' => $relation->getTargetPrimaryColumn(),
                        'extraProperties' => $relation->getExtraProperties(),
                        'primaryColumns' => $relation->getPrimaryColumns(),
                    ];

                    continue;
                }

                $existing = $definitions[$tableName];
                if ($existing['ownerJoinColumn'] !== $relation->getOwnerJoinColumn() || $existing['targetJoinColumn'] !== $relation->getTargetJoinColumn()) {
                    throw new RuntimeException('Many-to-many misconfigured: conflicting mapping table definition');
                }
                $definitions[$tableName]['extraProperties'] = $this->mergeMappingTableProperties(
                    $existing['extraProperties'],
                    $relation->getExtraProperties(),
                    $tableName,
                );
            }
        }

        return $definitions;
    }

    /**
     * @param ReflectionEntity[] $entities
     * @return array<string, array{
     *     tableName: string,
     *     morphName: string,
     *     typeColumn: string,
     *     idColumn: string,
     *     targetColumn: string,
     *     targetTable: string,
     *     targetReferencedColumn: string,
     *     extraProperties: MappingTableProperty[],
     *     primaryColumns: string[],
     *     relations: ReflectionMorphToMany[]
     * }>
     */
    public function collectMorphToManyTables(array $entities): array
    {
        $definitions = [];
        foreach ($entities as $entity) {
            foreach ($entity->getEntityRelationProperties() as $relation) {
                if (!$relation instanceof ReflectionMorphToMany) {
                    continue;
                }

                $tableName = $relation->getTableName();
                $targetEntity = new ReflectionEntity($relation->getTargetEntity());

                if (!isset($definitions[$tableName])) {
                    $definitions[$tableName] = [
                        'tableName' => $tableName,
                        'morphName' => $relation->getMorphName(),
                        'typeColumn' => $relation->getTypeColumn(),
                        'idColumn' => $relation->getOwnerJoinColumn(),
                        'targetColumn' => $relation->getTargetJoinColumn(),
                        'targetTable' => $targetEntity->getTableName(),
                        'targetReferencedColumn' => $relation->getTargetPrimaryColumn(),
                        'extraProperties' => $relation->getExtraProperties(),
                        'primaryColumns' => $relation->getPrimaryColumns(),
                        'relations' => [$relation],
                    ];

                    continue;
                }

                // Merge relations for the same table (should have same morph name)
                $existing = $definitions[$tableName];
                if ($existing['morphName'] !== $relation->getMorphName()) {
                    throw new RuntimeException("Morph-to-many misconfigured: conflicting morph names for table '{$tableName}'");
                }

                $definitions[$tableName]['relations'][] = $relation;
                $definitions[$tableName]['extraProperties'] = $this->mergeMappingTableProperties(
                    $existing['extraProperties'],
                    $relation->getExtraProperties(),
                    $tableName,
                );
            }
        }

        return $definitions;
    }

    /**
     * @param MappingTableProperty[] $existing
     * @param MappingTableProperty[] $incoming
     * @return MappingTableProperty[]
     */
    private function mergeMappingTableProperties(array $existing, array $incoming, string $tableName): array
    {
        $properties = [];
        foreach ($existing as $property) {
            $properties[$property->name] = $property;
        }
        foreach ($incoming as $property) {
            if (!isset($properties[$property->name])) {
                $properties[$property->name] = $property;

                continue;
            }
            $current = $properties[$property->name];
            if ($current->type !== $property->type || $current->length !== $property->length || $current->defaultValue !== $property->defaultValue) {
                throw new RuntimeException(
                    sprintf(
                        'Many-to-many misconfigured: mapping table "%s" property "%s" conflicts between relations',
                        $tableName,
                        $property->name,
                    ),
                );
            }
            if ($property->nullable && !$current->nullable) {
                $properties[$property->name] = new MappingTableProperty(
                    name: $current->name,
                    type: $current->type,
                    nullable: true,
                    length: $current->length,
                    defaultValue: $current->defaultValue,
                );
            }
        }

        return array_values($properties);
    }
}
