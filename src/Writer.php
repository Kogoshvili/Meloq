<?php

namespace Kogoshvili\Meloq;

use Kogoshvili\Meloq\Models\Migration;
use Kogoshvili\Meloq\Models\MigrationType;

class Writer
{
    public static function create(string $tableName, Migration $migration, int $index = null): void
    {
        $type = self::migrationType($migration);

        file_put_contents(
            database_path('migrations/' . date('Y_m_d_His') . (!is_null($index) ? "_{$index}" : '') . "_{$type}" . "_{$tableName}_table.php"),
            $migration->body
        );
    }

    private static function migrationType(Migration $migration): string
    {
        return match ($migration->type) {
            MigrationType::CREATE => 'create',
            MigrationType::UPDATE => 'update',
            MigrationType::DROP => 'drop',
        };
    }
}
