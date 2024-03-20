<?php

namespace Kogoshvili\Meloq\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Foreign
{
    public function __construct()
    {
    }
}
