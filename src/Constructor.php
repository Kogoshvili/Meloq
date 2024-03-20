<?php

namespace Kogoshvili\Meloq;

use Kogoshvili\Meloq\Models\Migration;
use Kogoshvili\Meloq\Models\Model;
use Kogoshvili\Meloq\Models\MigrationType;

class Constructor
{
    protected array $columnsToUpdate;
    protected array $columnsToAdd;
    protected array $columnsToDrop;

    public function build(Model $model = null, Model $cachedModel = null): Migration|null
    {
        if (is_null($cachedModel) && is_null($model)) {
            return null;
        }

        if (is_null($cachedModel)) {
            return new Migration(MigrationType::CREATE, self::create($model));
        }

        if (is_null($model)) {
            return new Migration(MigrationType::DROP, self::drop($cachedModel));
        }

        $this->columnsToUpdate = array_intersect_key($model->columns, $cachedModel->columns);
        $this->columnsToAdd = array_diff_key($model->columns, $cachedModel->columns);
        $this->columnsToDrop = array_diff_key($cachedModel->columns, $model->columns);

        if (!self::hasChanged($model, $cachedModel)) {
            return null;
        }

        return new Migration(MigrationType::UPDATE, self::update($model, $cachedModel));
    }

    public function create(Model $model): string
    {
        $columnUpdate = '';
        foreach ($model->columns as $column) {
            $columnUpdate .= $column->create();
        }

        if ($model->table->primary) {
            $columnUpdate .= $model->table->makePrimary($model->columns);
        }

        return <<<EOT
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
    * Run the migrations.
    */
    public function up(): void
    {
        {$model->table->create()}\n{$columnUpdate}
        });
    }

    /**
    * Reverse the migrations.
    */
    public function down(): void
    {
        {$model->table->drop()}
    }
};
EOT;
    }

    public function drop(Model $model): string
    {
        $columnUpdate = '';
        foreach ($model->columns as $column) {
            $columnUpdate .= $column->create();
        }

        if ($model->table->primary) {
            $columnUpdate .= $model->table->makePrimary($model->columns);
        }

        return <<<EOT
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
    * Run the migrations.
    */
    public function up(): void
    {
        {$model->table->drop()}
    }

    /**
    * Reverse the migrations.
    */
    public function down(): void
    {
        {$model->table->create()}\n{$columnUpdate}
        });
    }
};
EOT;
    }

    public function update(Model $model, Model $cachedModel): string
    {
        $upMethodBody = $this->updateUp($model, $cachedModel);
        $downMethodBody = $this->updateDown($model, $cachedModel);

        return <<<EOT
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        {$upMethodBody}
    }

    /**
    * Reverse the migrations.
    */
    public function down(): void
    {
        {$downMethodBody}
    }
};
EOT;
    }

    protected function updateUp(Model $model, Model $cachedModel): string
    {
        $columnUpdate = '';

        foreach ($this->columnsToDrop as $column) {
            $columnUpdate .= $column->drop();
        }

        foreach ($this->columnsToAdd as $column) {
            $columnUpdate .= $column->create();
        }

        foreach ($this->columnsToUpdate as $column) {
            $cachedColumn = $cachedModel->columns[$column->property];
            if (!$column->compare($cachedColumn)) {
                $columnUpdate .= $column->update($cachedColumn);
            }
        }

        $tableUpdate = '';
        if (!$model->table->compare($cachedModel->table)) {
            $tableUpdate = $model->table->update($cachedModel->table);
        }

        return <<<EOT
    {$model->table->modify()}\n{$columnUpdate}
            });
    {$tableUpdate}
EOT;
    }

    protected function updateDown(Model $model, Model $cachedModel): string
    {
        $columnUpdate = '';

        // Reverse of Up, thus create becomes drop
        foreach ($this->columnsToAdd as $column) {
            $columnUpdate .= $column->drop();
        }

        // Reverse of Up, thus drop becomes create
        foreach ($this->columnsToDrop as $column) {
            $columnUpdate .= $column->create();
        }

        foreach ($this->columnsToUpdate as $column) {
            $modelColumn = $model->columns[$column->property];
            if (!$column->compare($modelColumn)) {
                $columnUpdate .= $column->update($modelColumn);
            }
        }

        $tableUpdate = '';
        if (!$cachedModel->table->compare($model->table)) {
            $tableUpdate = $cachedModel->table->update($model->table);
        }

        return <<<EOT
    {$model->table->modify()}\n{$columnUpdate}
            });
    {$tableUpdate}
EOT;
    }

    public function hasChanged(Model $model, Model $cachedModel): bool
    {
        if (!$model->table->compare($cachedModel->table)) {
            return true;
        }

        if (!empty($this->columnsToAdd) || !empty($this->columnsToDrop)) {
            return true;
        }

        foreach ($this->columnsToUpdate as $column) {
            $cachedColumn = $cachedModel->columns[$column->property];
            if (!$column->compare($cachedColumn)) {
                return true;
            }
        }

        return false;
    }
}
