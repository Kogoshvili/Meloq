<?php

namespace Kogoshvili\Meloq;

use Kogoshvili\Meloq\Attributes\Method as MethodAttribute;
use ReflectionClass;
use Exception;
use Kogoshvili\Meloq\Models\Model;
use Kogoshvili\Meloq\Models\Table;
use Kogoshvili\Meloq\Models\Column;
use \Kogoshvili\Meloq\Attributes\Column as ColumnAttribute;
use \Kogoshvili\Meloq\Attributes\Table as TableAttribute;
use \Kogoshvili\Meloq\Attributes\Ignore as IgnoreAttribute;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\Relation;

class Reader
{
    public array $builtInMethods;
    public array $ignoreClasses;

    public array $typeMethodMappings = [
        'int' => 'integer',
        'bool' => 'boolean',
    ];

    public string $class;

    public function __construct(string $class)
    {
        $this->class = $class;
        $this->builtInMethods = config('meloq.builtInMethods');
        $this->ignoreClasses = config('meloq.ignoreClasses');
    }


    public function describe(): Model|null
    {
        $reflectionClass = new ReflectionClass($this->class);

        if (!empty($reflectionClass->getAttributes(IgnoreAttribute::class))) {
            return null;
        }

        $table = $this->describeClass($reflectionClass);
        $columns = $this->describeProperties($reflectionClass);

        if ($table->primary) {
            $columnExists = false;
            foreach ($columns as $column) {
                if ($column->property === $table->primary) {
                    $column->primaryKey = true;
                    $columnExists = true;
                } else {
                    $column->primaryKey = false;
                }
            }

            if (!$columnExists) {
                $columns[$table->primary] = new Column(
                    property: $table->primary,
                    name: strtolower($table->primary),
                    type: 'integer',
                    nullable: false
                );
            }
        }

        $relations = [];
        foreach ($columns as $column) {
            if ($column->foreignKey) {
                $relations[] = $column->referenceTable;
            }
        }

        return new Model($table->class, $table, $columns, $relations);
    }


    protected function describeProperties(ReflectionClass $reflectionClass): array
    {
        $columns = [];

        foreach ($reflectionClass->getProperties() as $property) {
            if (!$property->isPublic()) continue;
            // if (!$property->isStatic()) continue;

            if (!empty($property->getAttributes(IgnoreAttribute::class))) continue;

            if (in_array($property->class, $this->ignoreClasses)) continue;

            $column = $this->describeProperty($property);
            $columns[$column->property] = $column;
        }

        // get methods
        foreach ($reflectionClass->getMethods() as $method) {
            if (!$method->isPublic()) continue;

            if (!empty($method->getAttributes(IgnoreAttribute::class))) continue;

            if (in_array($method->class, $this->ignoreClasses)) continue;

            $column = $this->describeMethod($method);
            if (is_null($column)) continue;
            $columns[$column->property] = $column;
        }

        return $columns;
    }

    public function extraModels(array $models)
    {
        $extraModels = [];

        foreach ($models as $model) {
            $class = $model->table->class;
            $reflectionClass = new ReflectionClass($class);

            foreach ($reflectionClass->getMethods() as $method) {
                if (!$method->isPublic()) continue;

                if (!empty($method->getAttributes(IgnoreAttribute::class))) continue;

                if (in_array($method->class, $this->ignoreClasses)) continue;

                $methodReturnType = $method->getReturnType()?->getName();

                if ($methodReturnType == 'Illuminate\Database\Eloquent\Relations\BelongsToMany') {
                    $relation = $method->invoke(new $method->class);
                    $relatedClass = get_class($relation->getRelated());
                    $extraModels[] = $this->buildManyToManyModel($relation, $model->table->class, $relatedClass);;
                }
            }
        }

        return $extraModels;
    }

    protected function buildManyToManyModel(Relation $relation, string $class, string $relatedClass): Model
    {
        $relatedClassReflection = new ReflectionClass($relatedClass);
        $relatedRelation = null;

        foreach ($relatedClassReflection->getMethods() as $method) {
            $returnType = $method->getReturnType()?->getName();
            if (Str::startsWith($returnType, 'Illuminate\Database\Eloquent\Relations')) {
                $potentialRelation = $method->invoke(new $method->class);
                $potentialRelationClass = get_class($potentialRelation->getRelated());
                if ($potentialRelationClass === $class) {
                    $relatedRelation = $potentialRelation;
                    break;
                }
            }
        }

        if (is_null($relatedRelation)) {
            throw new Exception("Relation method not found on class {$relatedClass}");
        }

        $tableName = $relation->getTable();

        $columns = [
            new Column(
                property: null,
                name: $relation->getForeignPivotKeyName(),
                type: 'integer',
                nullable: false,
                default: null,
                foreignKey: $relation->getForeignPivotKeyName(),
                referenceKey: $relation->getParentKeyName(),
                referenceTable: $tableName
            ),
            new Column(
                property: null,
                name: $relation->getRelatedPivotKeyName(),
                type: 'integer',
                nullable: false,
                default: null,
                foreignKey: $relation->getRelatedPivotKeyName(),
                referenceKey: $relation->getRelatedKeyName(),
                referenceTable: $tableName
            )
        ];

        $thisClass = str_replace('\\', '_', get_class($relation->getParent()));
        $relatedClass = str_replace('\\', '_', get_class($relation->getRelated()));
        $table = new Table(null, $tableName, null);
        return new Model("{$thisClass}-{$relatedClass}", $table, $columns);
    }

    protected function describeMethod($method): Column|Model|null
    {
        // check if method returns class under namespace of \Illuminate\Database\Eloquent\Relations
        $methodReturnType = $method->getReturnType()?->getName();

        // Will cover One to Many, Many to One, One to One
        if ($methodReturnType == 'Illuminate\Database\Eloquent\Relations\BelongsTo')
        {
            $relation = $method->invoke(new $method->class);

            return new Column(
                property: $method->getName(),
                name: strtolower($method->getName()),
                type: 'integer',
                nullable: true,
                default: null,
                foreignKey: $relation->getForeignKeyName(),
                referenceKey: $relation->getOwnerKeyName(),
                referenceTable: $relation->getRelated()->getTable()
            );
        }

        return null;
    }

    protected function describeProperty($property): Column
    {
        $propertyType = $property->getType();
        $attributes = $property->getAttributes(ColumnAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);
        $propertyName = $property->getName();

        if (is_null($propertyType) && empty($attributes)) {
            throw new Exception("Property type is not defined for {$propertyName}");
        }

        $propertyTypeName = $propertyType?->getName();
        $propertyTypeName = $this->typeMethodMappings[$propertyTypeName] ?? $propertyTypeName;
        $propertyTypeIsBuiltin = $propertyType?->isBuiltin();
        $value = null;

        if (!$propertyTypeIsBuiltin) {
            $reflectionClass = new ReflectionClass($propertyTypeName);
            if ($reflectionClass->isEnum()) {
                $propertyTypeName = 'enum';
                $value = [];
                foreach ($reflectionClass->getConstants() as $enumValue) {
                    $value[] = $enumValue->name;
                }
            }
        }

        if (empty($attributes) && !is_null($propertyTypeName)) {
            if (!in_array($propertyTypeName, $this->builtInMethods)) {
                throw new Exception("Property type {$propertyTypeName} is not supported");
            }
        }

        $propertyIsNullable = $propertyType->allowsNull();
        $propertyDefault = $property->hasDefaultValue() ? $property->getDefaultValue() : null;

        if (empty($attributes)) {
            return new Column(
                property: $propertyName,
                name: strtolower($propertyName),
                type: $propertyTypeName,
                nullable: $propertyIsNullable,
                default: $propertyDefault,
                value: $value
            );
        }

        $columnProperties = (array) $attributes[0]->newInstance();

        $columnProperties['name'] ??= strtolower($propertyName);
        $columnProperties['type'] ??= $propertyTypeName;
        $columnProperties['nullable'] ??= $propertyIsNullable;
        $columnProperties['default'] ??= $propertyDefault;
        $columnProperties['value'] ??= $value;

        return new Column(
            $propertyName,
            ...$columnProperties
        );
    }

    protected function describeClass(ReflectionClass $reflectionClass): Table
    {
        $classAttributes = $reflectionClass->getAttributes(TableAttribute::class);

        $tableName = null;
        $primary = null;

        if (!empty($classAttributes)) {
            $columnProperties = $classAttributes[0]->getArguments();
            $tableName ??= $columnProperties['name'] ?? null;
            $primary ??= $columnProperties['primary'] ?? null;
        }

        $tableName ??= $reflectionClass->getProperty('table')?->getDefaultValue();
        $tableName ??= Str::plural(strtolower($reflectionClass->getShortName()));

        return new Table($reflectionClass->getName(), $tableName, $primary);
    }
}
/*
   protected function describeMethod($method)
    {
        // check if method returns class under namespace of \Illuminate\Database\Eloquent\Relations
        $methodReturnType = $method->getReturnType()?->getName();

        if (Str::startsWith($methodReturnType, 'Illuminate\Database\Eloquent\Relations')) {
            // invoke method to get the relation
            $relation = $method->invoke(new $method->class);
            // $relation->foreignKey;
            // $relation->ownerKey;
            // $relation->relationName;


            $relationClass = get_class($relation->getRelated());
            // Get methods of the related class and find relation method that returns the current class
            $reflectionClass = new ReflectionClass($relationClass);
            $relationMethodType = null;

            foreach ($reflectionClass->getMethods() as $method) {
                $returnType = $method->getReturnType()?->getName();
                if (Str::startsWith($returnType, 'Illuminate\Database\Eloquent\Relations')) {
                    // invoke method to get the relation
                    $relation = $method->invoke(new $method->class);
                    $relationClass = get_class($relation->getRelated());
                    if ($relationClass === $this->class) {
                        $relationMethodType = $returnType;
                        break;
                    }
                }
            }

            if (is_null($relationMethodType)) {
                throw new Exception("Relation method not found for {$this->class}");
            }

            // Based on $methodReturnType and $relationMethodType, we can determine the relation type
            $relationType = null;
            if ($methodReturnType === 'Illuminate\Database\Eloquent\Relations\BelongsTo' && $relationMethodType === 'Illuminate\Database\Eloquent\Relations\HasMany') {
                $relationType = 'oneToMany';
            } else if ($methodReturnType === 'Illuminate\Database\Eloquent\Relations\HasMany' && $relationMethodType === 'Illuminate\Database\Eloquent\Relations\BelongsTo') {
                $relationType = 'manyToOne';
            } else if ($methodReturnType === 'Illuminate\Database\Eloquent\Relations\HasOne' && $relationMethodType === 'Illuminate\Database\Eloquent\Relations\BelongsTo') {
                $relationType = 'oneToOne';
            } else if ($methodReturnType === 'Illuminate\Database\Eloquent\Relations\BelongsToMany' && $relationMethodType === 'Illuminate\Database\Eloquent\Relations\BelongsToMany') {
                $relationType = 'manyToMany';
            }

            switch ($relationType) {
                case 'oneToMany':
                case 'manyToOne':
                case 'oneToOne':
                    return new Column(
                        property: $method->getName(),
                        name: strtolower($method->getName()),
                        type: 'integer',
                        nullable: true,
                        default: null,
                        foreignKey: $relation->getForeignKeyName(),
                        referenceKey: $relation->getOwnerKeyName(),
                        referenceTable: $relation->getRelated()->getTable()
                    );
            }
        }
    }
*/
