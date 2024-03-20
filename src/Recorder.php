<?php

namespace Kogoshvili\Meloq;

use Kogoshvili\Meloq\Models\Model;

class Recorder
{
    public $folderPath;
    public $fileExtension = 'json';

    public function __construct()
    {
        $this->folderPath = database_path('meloq');
    }

    public function get(string $class): Model|null
    {
        $filename = self::getFilename($class);
        $fullPath = $this->getFullPath($filename);

        if (!file_exists($fullPath)) return null;

        $json = file_get_contents($fullPath);
        $stdObject = json_decode($json);
        return Model::build($stdObject);
    }

    public function set(Model $model): void
    {
        $filename = self::getFilename($model->name);
        $fullPath = $this->getFullPath($filename);

        $this->handleFolder();

        $json = json_encode($model, JSON_PRETTY_PRINT);

        file_put_contents($fullPath, $json);
    }

    protected static function getFilename($class): string
    {
        return str_replace('\\', '_', $class);
    }

    protected function getFullPath($filename): string
    {
        return "{$this->folderPath}/{$filename}.{$this->fileExtension}";
    }

    protected function handleFolder(): void
    {
        if (!file_exists($this->folderPath)) {
            mkdir($this->folderPath);
        }
    }

    public function clear(): void
    {
        $this->handleFolder();

        $files = glob($this->folderPath . '/*');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
