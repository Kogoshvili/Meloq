<?php

namespace Kogoshvili\Meloq\Interfaces;

interface ICUMD
{
    public function create(): string;
    public function update($cache): string; // self
    public function modify(): string; // other
    public function drop(): string;
}
