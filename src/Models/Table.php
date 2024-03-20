<?php

namespace Kogoshvili\Meloq\Models;

use Kogoshvili\Meloq\Interfaces\IComparable;
use Kogoshvili\Meloq\Interfaces\ICUMD;
use Kogoshvili\Meloq\Interfaces\IBuildable;
use Kogoshvili\Meloq\Attributes\Table as TableAttribute;

class Table extends TableAttribute
    implements IComparable, ICUMD, IBuildable
{
    public ?string $class;

    public function __construct(
        ?string $class,
        string $name,
        mixed $primary, // Property name or names as an array
    ) {
        parent::__construct(
            name: $name,
            primary: $primary,
        );

        $this->class = $class;
    }

    public function modify(): string
    {
        return "Schema::table('$this->name', function (Blueprint \$table) {";
    }

    public function create(): string
    {
        return "Schema::create('$this->name', function (Blueprint \$table) {";
    }

    public function update($table): string
    {
        return "Schema::rename('$this->name', '$table->name');";
    }

    public function drop(): string
    {
        return "Schema::dropIfExists('$this->name');";
    }

    public function compare($table): bool
    {
        if ($this->class !== $table->class) {
            throw new \Exception("Classes are not the same.");
        }

        return $this->name === $table->name;
    }

    public static function build(\stdClass $object): Table
    {
        $array = (array) $object;
        return new Table(...$array);
    }

    public function makePrimary(array $columns): string
    {
        if (is_array($this->primary)) {
            $primaryColumns = array_filter($this->primary, fn($key) => $columns[$key]);
            $string = implode("', '", $primaryColumns);
            return "            \$table->primary(['{$string}']);\n";
        }

        $primaryColumn = $columns[$this->primary];
        return "            \$table->primary('{$primaryColumn->name}');\n";
    }
}
