<?php

namespace Kogoshvili\Meloq\Models;

use Kogoshvili\Meloq\Interfaces\IComparable;
use Kogoshvili\Meloq\Interfaces\ICUMD;
use Kogoshvili\Meloq\Interfaces\IBuildable;
use Kogoshvili\Meloq\Attributes\Column as ColumnAttribute;

class Column extends ColumnAttribute
    implements IComparable, ICUMD, IBuildable
{
    public ?string $property;

    public function __construct(
        ?string $property,
        ?string $name = null,
        ?string $type = null,
        ?string $comment = null,
        ?int $precision = null,
        ?int $scale = null,
        bool $nullable = false,
        bool $unique = false,
        bool $primary = false,
        bool $increment = false,
        bool $index = false,
        mixed $default = null,
        mixed $value = null,
        ?string $foreignKey = null,
        ?string $referenceKey = null,
        ?string $referenceTable = null
    )
    {
        parent::__construct(
            name: $name,
            type: $type,
            comment: $comment,
            precision: $precision,
            scale: $scale,
            nullable: $nullable,
            unique: $unique,
            primary: $primary,
            increment: $increment,
            index: $index,
            default: $default,
            value: $value,
            foreignKey: $foreignKey,
            referenceKey: $referenceKey,
            referenceTable: $referenceTable
        );

        $this->property = $property;
    }

    public function modify(): string
    {
        return "            \$table->{$this->type}('{$this->name}');\n";
    }

    public function create(): string
    {
        if ($this->foreignKey !== null) {
            $result = "            \$table->foreignId('{$this->foreignKey}')->references('{$this->referenceKey}')->on('{$this->referenceTable}')";
            return $result. ";\n";
        }


        $result = "            \$table->{$this->type}('{$this->name}'";

        if ($this->value !== null) {
            if (is_array($this->value)) {
                $string = implode("', '", $this->value);
                $result .= ", ['{$string}']";
            } else {
                $result .= ", {$this->value}";
            }
        }

        if ($this->precision !== null) {
            $result .= ", {$this->precision}";
        }

        if ($this->scale !== null) {
            $result .= ", {$this->scale}";
        }

        $result .= ")";

        if ($this->nullable) {
            $result .= "->nullable()";
        }

        if ($this->unique) {
            $result .= "->unique()";
        }

        if ($this->primary) {
            $result .= "->primary()";
        }

        if ($this->default !== null) {
            $result .= "->default({$this->default})";
        }

        if ($this->comment !== null) {
            $result .= "->comment('{$this->comment}')";
        }

        if ($this->increment) {
            $result .= "->autoIncrement()";
        }

        if ($this->index) {
            $result .= "->index()";
        }

        return $result. ";\n";
    }

    public function update($cachedColumn): string
    {
        $result = substr($this->create(), 0, -2); // removing ";\n"
        $result. "->change();\n";

        if ($this->name !== $cachedColumn->name) {
            $result .= "            \$table->renameColumn('{$cachedColumn->name}', '{$this->name}');\n";
        }

        return $result;
    }

    public function drop(): string
    {
        return "            \$table->dropColumn('{$this->name}');\n";
    }

    public function compare($column): bool
    {
        if ($this->property !== $column->property) {
            throw new \Exception("Classes are not the same.");
        }

        if ($this->type != $column->type) {
            throw new \Exception("Column type changing not supported: {$this->name}");
        }

        if ($this->name !== $column->name) return false;
        if ($this->nullable !== $column->nullable) return false;

        return true;
    }

    public static function build(\stdClass $object): Column
    {
        $array = (array) $object;
        return new Column(...$array);
    }
}
