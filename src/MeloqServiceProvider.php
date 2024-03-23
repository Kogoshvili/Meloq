<?php

namespace Kogoshvili\Meloq;

use Illuminate\Support\ServiceProvider;

class MeloqServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/meloq.php', 'meloq'
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Create::class,
                Console\Record::class,
            ]);

            $this->publishes([
                __DIR__.'/config/meloq.php' => config_path('meloq.php'),
            ], 'meloq-config');
        }
    }
}
