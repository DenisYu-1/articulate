<?php

namespace Articulate\Modules\QueryBuilder;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Attributes\Reflection\ReflectionProperty;
use Articulate\Connection;
use Articulate\Exceptions\TransactionRequiredException;
use Articulate\Modules\EntityManager\EntityMetadataRegistry;
use InvalidArgumentException;

class DmlOperationHandler {
    private ?string $dmlCommand = null;

    private array $insertColumns = [];

    private array $insertValues = [];

    private array $updateSet = [];

    private ?object $dmlEntity = null;

    private array $returning = [];

    public function __construct(
        private readonly ?EntityMetadataRegistry $metadataRegistry
    ) {
    }

    public function insert(
        object|array|string $entitiesOrTable,
        DmlContext $context
    ): void {
        $this->dmlCommand = 'insert';
        $this->insertColumns = [];
        $this->insertValues = [];

        if (is_string($entitiesOrTable)) {
            if (empty($context->getFrom())) {
                $context->setFrom($entitiesOrTable);
            }

            return;
        }

        $entitiesArray = is_array($entitiesOrTable) ? $entitiesOrTable : [$entitiesOrTable];

        if (empty($entitiesArray)) {
            throw new InvalidArgumentException('INSERT requires at least one entity');
        }

        $firstEntity = $entitiesArray[0];
        if (!is_object($firstEntity)) {
            throw new InvalidArgumentException('INSERT entities must be objects');
        }

        $this->dmlEntity = $firstEntity;

        if ($context->getEntityClass() === null) {
            $context->setEntityClass($firstEntity::class);
        }

        if (empty($context->getFrom())) {
            $context->setFrom($this->resolveTableName($firstEntity::class));
        }

        foreach ($entitiesArray as $entity) {
            $data = $this->extractEntityInsertData($entity);
            if (empty($this->insertColumns)) {
                $this->insertColumns = $data['columns'];
            }
            $this->insertValues[] = $data['values'];
        }
    }

    public function values(array $values): void
    {
        if ($this->dmlCommand !== 'insert') {
            throw new InvalidArgumentException('values() can only be used with insert()');
        }

        if (empty($this->insertColumns)) {
            $this->insertColumns = array_keys($values);
        }

        if (count($values) !== count($this->insertColumns)) {
            throw new InvalidArgumentException('Number of values must match number of columns');
        }

        $orderedValues = [];
        foreach ($this->insertColumns as $column) {
            $orderedValues[] = $values[$column];
        }

        $this->insertValues[] = $orderedValues;
    }

    public function update(object|string $entityOrTable, DmlContext $context): void
    {
        $this->dmlCommand = 'update';
        $this->updateSet = [];

        if (is_object($entityOrTable)) {
            $this->dmlEntity = $entityOrTable;

            if ($context->getEntityClass() === null) {
                $context->setEntityClass($entityOrTable::class);
            }

            if (empty($context->getFrom())) {
                $context->setFrom($this->resolveTableName($entityOrTable::class));
            }

            $whereClause = $this->buildEntityWhereClause($entityOrTable);
            if (!empty($whereClause['clause'])) {
                $context->addWhere($whereClause['clause'], ...$whereClause['values']);
            }
        } else {
            if (empty($context->getFrom())) {
                $context->setFrom($entityOrTable);
            }
        }
    }

    public function set(string|array $column, mixed $value = null): void
    {
        if ($this->dmlCommand !== 'update') {
            throw new InvalidArgumentException('set() can only be used with update()');
        }

        if (is_array($column)) {
            foreach ($column as $col => $val) {
                $this->updateSet[$col] = $val;
            }
        } else {
            $this->updateSet[$column] = $value;
        }
    }

    public function delete(object|string $entityOrTable, DmlContext $context): void
    {
        $this->dmlCommand = 'delete';

        if (is_object($entityOrTable)) {
            $this->dmlEntity = $entityOrTable;

            if ($context->getEntityClass() === null) {
                $context->setEntityClass($entityOrTable::class);
            }

            if (empty($context->getFrom())) {
                $context->setFrom($this->resolveTableName($entityOrTable::class));
            }

            $whereClause = $this->buildEntityWhereClause($entityOrTable);
            if (!empty($whereClause['clause'])) {
                $context->addWhere($whereClause['clause'], ...$whereClause['values']);
            }
        } else {
            if (empty($context->getFrom())) {
                $context->setFrom($entityOrTable);
            }
        }
    }

    public function returning(string ...$columns): void
    {
        $this->returning = array_merge($this->returning, $columns);
    }

    public function getCommand(): ?string
    {
        return $this->dmlCommand;
    }

    public function getInsertColumns(): array
    {
        return $this->insertColumns;
    }

    public function getInsertValues(): array
    {
        return $this->insertValues;
    }

    public function getUpdateSet(): array
    {
        return $this->updateSet;
    }

    public function getReturning(): array
    {
        return $this->returning;
    }

    public function reset(): void
    {
        $this->dmlCommand = null;
        $this->insertColumns = [];
        $this->insertValues = [];
        $this->updateSet = [];
        $this->dmlEntity = null;
        $this->returning = [];
    }

    public function execute(
        Connection $connection,
        SqlCompiler $sqlCompiler,
        array $where,
        string $from,
        bool $lockForUpdate,
        callable $expandInPlaceholders
    ): mixed {
        if ($lockForUpdate && !$connection->inTransaction()) {
            throw new TransactionRequiredException('lock() requires an active transaction');
        }

        if ($this->dmlCommand === 'insert') {
            if (empty($from)) {
                throw new InvalidArgumentException('INSERT requires a table name');
            }

            [$sql, $params] = $sqlCompiler->compileInsert(
                $from,
                $this->insertColumns,
                $this->insertValues,
                $this->returning
            );
        } elseif ($this->dmlCommand === 'update') {
            if (empty($from)) {
                throw new InvalidArgumentException('UPDATE requires a table name');
            }

            [$sql, $params] = $sqlCompiler->compileUpdate(
                $from,
                $this->updateSet,
                $where,
                $this->returning
            );
        } elseif ($this->dmlCommand === 'delete') {
            if (empty($from)) {
                throw new InvalidArgumentException('DELETE requires a table name');
            }

            [$sql, $params] = $sqlCompiler->compileDelete(
                $from,
                $where,
                $this->returning
            );
        } else {
            throw new InvalidArgumentException('No DML command set');
        }

        if ($this->dmlCommand !== 'insert') {
            [$sql, $params] = $expandInPlaceholders($sql, $params);
        }

        $statement = $connection->executeQuery($sql, $params);

        if ($this->dmlCommand === 'insert' && !empty($this->returning)) {
            return $statement->fetchAll();
        }

        if ($this->dmlCommand === 'insert' && count($this->insertValues) === 1) {
            $driverName = $connection->getDriverName();
            if ($driverName === Connection::PGSQL) {
                if (!empty($this->returning)) {
                    return $statement->fetchAll();
                }
            }

            $lastInsertId = $connection->lastInsertId();
            if ($lastInsertId !== false) {
                return (int) $lastInsertId;
            }
        }

        if ($this->dmlCommand === 'update' && !empty($this->returning)) {
            return $statement->fetchAll();
        }

        if ($this->dmlCommand === 'delete' && !empty($this->returning)) {
            return $statement->fetchAll();
        }

        return $statement->rowCount();
    }

    private function resolveTableName(string $entityClass): string
    {
        if ($this->metadataRegistry) {
            return $this->metadataRegistry->getTableName($entityClass);
        }

        $className = basename(str_replace('\\', '/', $entityClass));

        return strtolower($className) . 's';
    }

    private function extractEntityInsertData(object $entity): array
    {
        $reflectionEntity = new ReflectionEntity($entity::class);

        $properties = array_filter(
            iterator_to_array($reflectionEntity->getEntityProperties()),
            fn ($property) => $property instanceof ReflectionProperty
        );

        $columns = [];
        $values = [];

        foreach ($properties as $property) {
            $columnName = $property->getColumnName();

            $value = $property->getValue($entity);

            if ($property->isPrimaryKey() && $value === null) {
                continue;
            }

            if ($value === null && !$property->isNullable() && $property->getDefaultValue() === null) {
                continue;
            }

            $columns[] = $columnName;
            $values[] = $value;
        }

        $this->addMorphToColumns($entity, $columns, $columns, $values);

        return ['columns' => $columns, 'values' => $values];
    }

    private function buildEntityWhereClause(object $entity): array
    {
        $reflectionEntity = new ReflectionEntity($entity::class);
        $whereParts = [];
        $whereValues = [];

        $primaryKeyColumns = $reflectionEntity->getPrimaryKeyColumns();
        if (!empty($primaryKeyColumns)) {
            foreach ($primaryKeyColumns as $pkColumn) {
                $pkProperty = null;
                foreach (iterator_to_array($reflectionEntity->getEntityProperties()) as $property) {
                    if ($property->getColumnName() === $pkColumn && $property instanceof ReflectionProperty) {
                        $pkProperty = $property;

                        break;
                    }
                }

                if ($pkProperty === null) {
                    $reflectionEntity = new ReflectionEntity($entity::class);
                    foreach (iterator_to_array($reflectionEntity->getEntityProperties()) as $property) {
                        if ($property instanceof ReflectionProperty && $property->getFieldName() === 'id') {
                            $idValue = $property->getValue($entity);
                            if ($idValue !== null) {
                                $whereParts[] = 'id = ?';
                                $whereValues[] = $idValue;
                            }

                            break;
                        }
                    }

                    continue;
                }

                $pkValue = $pkProperty->getValue($entity);

                $whereParts[] = "{$pkColumn} = ?";
                $whereValues[] = $pkValue;
            }
        } else {
            $reflectionEntity = new ReflectionEntity($entity::class);
            foreach (iterator_to_array($reflectionEntity->getEntityProperties()) as $property) {
                if ($property instanceof ReflectionProperty && $property->getFieldName() === 'id') {
                    $idValue = $property->getValue($entity);
                    if ($idValue !== null) {
                        $whereParts[] = 'id = ?';
                        $whereValues[] = $idValue;
                    }

                    break;
                }
            }
        }

        return ['clause' => implode(' AND ', $whereParts), 'values' => $whereValues];
    }

    private function addMorphToColumns(object $entity, array &$columns, array &$placeholders, array &$values): void
    {
        if ($this->metadataRegistry === null) {
            return;
        }

        try {
            $metadata = $this->metadataRegistry->getMetadata($entity::class);
            $relations = $metadata->getRelations();

            foreach ($relations as $relation) {
                if ($relation->isMorphTo()) {
                    $propertyName = $relation->getPropertyName();
                    $reflectionEntity = new ReflectionEntity($entity::class);
                    $relatedProperty = null;
                    foreach (iterator_to_array($reflectionEntity->getEntityProperties()) as $property) {
                        if ($property instanceof ReflectionProperty && $property->getFieldName() === $propertyName) {
                            $relatedProperty = $property;

                            break;
                        }
                    }
                    if ($relatedProperty === null) {
                        continue;
                    }
                    $relatedEntity = $relatedProperty->getValue($entity);

                    if ($relatedEntity !== null) {
                        $morphType = $relatedEntity::class;
                        $relatedId = $this->extractEntityId($relatedEntity);

                        $columns[] = $relation->getMorphTypeColumnName();
                        $placeholders[] = '?';
                        $values[] = $morphType;

                        $columns[] = $relation->getMorphIdColumnName();
                        $placeholders[] = '?';
                        $values[] = $relatedId;
                    }
                }
            }
        } catch (InvalidArgumentException $e) {
        }
    }

    private function extractEntityId(object $entity): mixed
    {
        $reflectionEntity = new ReflectionEntity($entity::class);

        foreach (iterator_to_array($reflectionEntity->getEntityFieldsProperties()) as $property) {
            if ($property->isPrimaryKey() && $property instanceof ReflectionProperty) {
                return $property->getValue($entity);
            }
        }

        $reflectionEntity = new ReflectionEntity($entity::class);
        foreach (iterator_to_array($reflectionEntity->getEntityProperties()) as $property) {
            if ($property instanceof ReflectionProperty && $property->getFieldName() === 'id') {
                return $property->getValue($entity);
            }
        }

        return null;
    }
}
