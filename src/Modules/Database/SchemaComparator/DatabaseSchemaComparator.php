<?php

namespace Articulate\Modules\Database\SchemaComparator;

use Articulate\Attributes\Reflection\ReflectionEntity;
use Articulate\Modules\Database\SchemaComparator\Comparators\ColumnComparator;
use Articulate\Modules\Database\SchemaComparator\Comparators\EntityTableComparator;
use Articulate\Modules\Database\SchemaComparator\Comparators\ForeignKeyComparator;
use Articulate\Modules\Database\SchemaComparator\Comparators\IndexComparator;
use Articulate\Modules\Database\SchemaComparator\Comparators\MappingTableComparator;
use Articulate\Modules\Database\SchemaComparator\Models\TableCompareResult;
use Articulate\Modules\Database\SchemaComparator\RelationValidators\RelationValidatorFactory;
use Articulate\Modules\Database\SchemaReader\DatabaseSchemaReaderInterface;
use Articulate\Schema\SchemaNaming;

class DatabaseSchemaComparator {
    private readonly SchemaComparisonCoordinator $coordinator;

    public function __construct(
        private readonly DatabaseSchemaReaderInterface $databaseSchemaReader,
        private readonly SchemaNaming $schemaNaming,
        private readonly RelationValidatorFactory $relationValidatorFactory = new RelationValidatorFactory(),
    ) {
        $this->coordinator = new SchemaComparisonCoordinator(
            $this->databaseSchemaReader,
            new RelationDefinitionCollector($this->relationValidatorFactory),
            new EntityTableComparator(
                $this->databaseSchemaReader,
                new ColumnComparator(),
                new IndexComparator(),
                new ForeignKeyComparator($this->schemaNaming, $this->relationValidatorFactory),
            ),
            new MappingTableComparator(
                $this->databaseSchemaReader,
                $this->schemaNaming,
                new IndexComparator(),
            ),
        );
    }

    /**
     * @param ReflectionEntity[] $entities
     * @return iterable<TableCompareResult>
     */
    public function compareAll(array $entities): iterable
    {
        return $this->coordinator->compareAll($entities);
    }
}
