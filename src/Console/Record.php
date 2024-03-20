<?php

namespace Kogoshvili\Meloq\Console;

use Illuminate\Console\Command;
use Kogoshvili\Meloq\Reader;
use Kogoshvili\Meloq\Recorder;

class Record extends Command
{
    protected $signature = 'meloq:record';

    protected $description = 'Create Records';

    public function handle()
    {
        $this->info('Start');

        $recorder = new Recorder();
        $class = \App\Models\Book::class;
        $reader = new Reader();
        $model = $reader->describe($class);
        $recorder->set($model);
    }
}
