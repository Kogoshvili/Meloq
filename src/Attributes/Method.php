<?php

namespace Kogoshvili\Meloq\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Method
{
    public function __construct(
        public string $type, // e.g. BelongsTo
        public string $class // e.g. Book::class
    ) { }
}
