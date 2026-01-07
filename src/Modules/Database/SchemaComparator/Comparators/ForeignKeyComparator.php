<?php

namespace Articulate\Modules\Database\SchemaComparator\Comparators;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionRelation;
use Articulate\Modules\Database\SchemaComparator\Models\CompareResult;
use Articulate\Modules\Database\SchemaComparator\Models\ForeignKeyCompareResult;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\RelationValidatorFactory;
use Articulate\Schema\SchemaNaming;

readonly class ForeignKeyComparator {
    public function __construct(
        private SchemaNaming $schemaNaming,
        private RelationValidatorFactory $relationValidatorFactory,
    ) {
    }

    /**
     * @param array<string, array{
     *     type: string|null,
     *     nullable: bool,
     *     default: ?string,
     *     length: ?int,
     *     relation: ?ReflectionRelation,
     *     foreignKeyRequired: bool,
     *     referencedColumn: ?string,
     * }> $propertiesIndexed
     * @param array<string, array> $existingForeignKeys
     * @param array<string, bool> $foreignKeysToRemove
     * @param array<string, bool> $createdColumnsWithForeignKeys
     * @return array<ForeignKeyCompareResult>
     */
    public function compareForeignKeys(
        array $propertiesIndexed,
        array $existingForeignKeys,
        array &$foreignKeysToRemove,
        array $createdColumnsWithForeignKeys,
        string $tableName,
        bool $isNewTable = false,
    ): array {
        $foreignKeysByName = [];

        // Handle foreign keys for existing columns
        foreach ($propertiesIndexed as $columnName => $propertyData) {
            if (empty($existingForeignKeys) && $isNewTable) {
                continue;
            }
            if (!$propertyData['relation']) {
                continue;
            }
            $targetEntityClass = $propertyData['relation']->getTargetEntity();
            if ($targetEntityClass === null) {
                continue;
            }
            $targetEntity = new ReflectionEntity($targetEntityClass);
            $foreignKeyName = $this->schemaNaming->foreignKeyName($tableName, $targetEntity->getTableName(), $columnName);
            $foreignKeyExists = isset($existingForeignKeys[$foreignKeyName]);
            if ($propertyData['foreignKeyRequired']) {
                if (!$isNewTable && isset($createdColumnsWithForeignKeys[$columnName])) {
                    unset($foreignKeysToRemove[$foreignKeyName]);

                    continue;
                }
                $validator = $this->relationValidatorFactory->getValidator($propertyData['relation']);
                $validator->validate($propertyData['relation']);
                if (!$foreignKeyExists) {
                    $foreignKeysByName[$foreignKeyName] = new ForeignKeyCompareResult(
                        $foreignKeyName,
                        CompareResult::OPERATION_CREATE,
                        $columnName,
                        $targetEntity->getTableName(),
                        $propertyData['referencedColumn'],
                    );
                } else {
                    unset($foreignKeysToRemove[$foreignKeyName]);
                }
            } else {
                if ($foreignKeyExists) {
                    unset($foreignKeysToRemove[$foreignKeyName]);
                    $foreignKeysByName[$foreignKeyName] = new ForeignKeyCompareResult(
                        $foreignKeyName,
                        CompareResult::OPERATION_DELETE,
                        $existingForeignKeys[$foreignKeyName]['column'],
                        $existingForeignKeys[$foreignKeyName]['referencedTable'],
                        $existingForeignKeys[$foreignKeyName]['referencedColumn'],
                    );
                }
            }
        }

        // Handle remaining foreign keys to remove
        foreach (array_keys($foreignKeysToRemove) as $foreignKeyName) {
            $foreignKeysByName[$foreignKeyName] = new ForeignKeyCompareResult(
                $foreignKeyName,
                CompareResult::OPERATION_DELETE,
                $existingForeignKeys[$foreignKeyName]['column'],
                $existingForeignKeys[$foreignKeyName]['referencedTable'],
                $existingForeignKeys[$foreignKeyName]['referencedColumn'],
            );
        }

        return array_values($foreignKeysByName);
    }

    /**
     * Creates foreign keys for newly created columns.
     * @param array<string, array{
     *     type: string|null,
     *     nullable: bool,
     *     default: ?string,
     *     length: ?int,
     *     relation: ?ReflectionRelation,
     *     foreignKeyRequired: bool,
     *     referencedColumn: ?string,
     * }> $propertiesIndexed
     * @param array<string, bool> $createdColumnsWithForeignKeys
     * @return array<ForeignKeyCompareResult>
     */
    public function createForeignKeysForNewColumns(array $propertiesIndexed, array &$createdColumnsWithForeignKeys, string $tableName): array
    {
        $foreignKeysByName = [];

        foreach ($propertiesIndexed as $columnName => $data) {
            if ($data['relation'] && $data['foreignKeyRequired']) {
                $validator = $this->relationValidatorFactory->getValidator($data['relation']);
                $validator->validate($data['relation']);
                $targetEntityClass = $data['relation']->getTargetEntity();
                if ($targetEntityClass === null) {
                    continue;
                }
                $targetEntity = new ReflectionEntity($targetEntityClass);
                $fkName = $this->schemaNaming->foreignKeyName($tableName, $targetEntity->getTableName(), $columnName);
                $foreignKeysByName[$fkName] = new ForeignKeyCompareResult(
                    $fkName,
                    CompareResult::OPERATION_CREATE,
                    $columnName,
                    $targetEntity->getTableName(),
                    $data['referencedColumn'],
                );
                $createdColumnsWithForeignKeys[$columnName] = true;
            }
        }

        return array_values($foreignKeysByName);
    }
}
