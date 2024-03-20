<?php

namespace Kogoshvili\Meloq\Models;

class Migration
{
    public function __construct(
        public MigrationType $type,
        public string $body
    ) { }
}
enum MigrationType
{
    case CREATE;
    case UPDATE;
    case DROP;
}

