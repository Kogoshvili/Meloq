<?php

namespace Kogoshvili\Meloq\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public ?string $comment = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public bool $nullable = false,
        public bool $unique = false,
        public bool $primary = false,
        public bool $increment = false,
        public bool $index = false,
        public mixed $default = null,
        public mixed $value = null,
        public ?string $foreignKey = null,
        public ?string $referenceKey = null,
        public ?string $referenceTable = null,
    ) { }
}
