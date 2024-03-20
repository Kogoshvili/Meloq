<?php

namespace Kogoshvili\Meloq\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Timestamp extends Column
{
    public function __construct(
        ?string $name,
        ?int $precision
    )
    {
        parent::__construct(
            name: $name,
            type: "timestamp",
            precision: $precision
        );
    }
}
