<?php

namespace Kogoshvili\Meloq\Models;

use Kogoshvili\Meloq\Interfaces\IBuildable;

class Model implements IBuildable
{
    public function __construct(
        public string $name,
        public Table $table,
        public array $columns, // [propertyName => Column],
        public array $relations = []
    ) { }

    public static function build(\stdClass $object): Model
    {
        $table = Table::build($object->table);

        $columns = [];
        foreach ($object->columns as $column) {
            $columns[$column->property] = Column::build($column);
        }

        return new Model($object->name, $table, $columns);
    }
}
