<?php

namespace Kogoshvili\Meloq\Console;

use Illuminate\Console\Command;
use Kogoshvili\Meloq\Constructor;
use Kogoshvili\Meloq\Writer;
use Kogoshvili\Meloq\Reader;
use Kogoshvili\Meloq\Recorder;
use Kogoshvili\Meloq\Models\Model;

class Create extends Command
{
    protected $signature = 'meloq:create';

    protected $description = 'Create Migration';

    public function handle()
    {
        $this->info('Start');

        $classes = $this->getClassName();
        $recorder = new Recorder();
        $constructor = new Constructor();
        $models = [];

        foreach ($classes as $class) {
            $reader = new Reader($class);
            $model = $reader->describe();
            $primaryKeys = 0;

            foreach($model->columns as $column) {
                if ($column->primary) {
                    $primaryKeys++;
                }
            }

            if ($primaryKeys === 0) {
                foreach($model->columns as $column) {
                    if ($column->name === 'id') {
                        $column->primary = true;
                        $primaryKeys++;
                        break;
                    }
                }
            }

            if ($primaryKeys === 1) {
                $models[] = $model;
            } else {
                if ($primaryKeys > 1) {
                    $this->error("{$model->name}: Multiple primary keys are not supported.");
                } else {
                    $this->error("{$model->name}: Primary key is not set.");
                }
            }
        }

        // sort models by dependency tables, to avoid foreign key constraint errors
        $this->sortModels($models);

        $extraModels = $reader->extraModels($models);
        $models = array_merge($models, $extraModels);

        $models = array_values(array_filter($models, function($model) {
            return $model->columns !== [];
        }));

        foreach ($models as $index => $model) {
            $this->processModel($model, $recorder, $constructor, $index);
        }
    }

    protected function processModel(Model $model, Recorder $recorder, Constructor $constructor, int $index = null): void
    {
        $cachedModel = $recorder->get($model->name);
        $migration = $constructor->build($model, $cachedModel);

        if (!is_null($migration)) {
            Writer::create($model->table->name, $migration, $index);
        } else {
            $this->info("{$model->name}: No changes detected.");
        }

        $recorder->set($model);
    }

    protected function getClassName(string $namespace = 'App\Models'): array
    {
        $classes = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }

            $filePath = $file->getRealPath();
            $fileContent = file_get_contents($filePath);

            $namespacePattern = sprintf('/namespace\s+%s\s*;/', preg_quote($namespace));
            $classPattern = '/class\s+(\w+)/';

            if (preg_match($namespacePattern, $fileContent) && preg_match($classPattern, $fileContent, $matches)) {
                $className = $matches[1];
                $classes[] = $namespace . '\\' . $className;
            }
        }

        return $classes;
    }

    protected function sortModels(array &$models): void
    {
        usort($models, function($modelA, $modelB) {
            $modelBDependecies = $modelB->relations; // e.g. ['books', 'authors']
            $modelAName = $modelA->table->name; // e.g. 'authors'

            if ($modelBDependecies === []) {
                return 1;
            }

            if (in_array($modelAName, $modelBDependecies)) {
                return -1;
            }

            return 1;
        });
    }
}
