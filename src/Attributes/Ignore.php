<?php

namespace Kogoshvili\Meloq\Attributes;

use Attribute;

#[\Attribute(Attribute::TARGET_PROPERTY|Attribute::TARGET_CLASS)]
class Ignore
{
    public function __construct()
    {
    }
}
