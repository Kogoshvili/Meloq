<?php

namespace Kogoshvili\Meloq\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Table
{
    public function __construct(
        public ?string $name = null,
        public mixed $primary = null,
    ) { }
}
