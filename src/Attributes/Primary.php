<?php

namespace Kogoshvili\Meloq\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Primary extends Column
{
    public function __construct(
        ?string $name = null,
        ?string $type = null,
        ?bool $increment = true,
        ?string $comment = null,
    )
    {
        parent::__construct(
            name: $name,
            type: $type,
            nullable: false,
            unique: true,
            primary: true,
            comment: $comment,
            increment: $increment,
            index: true
        );
    }
}
