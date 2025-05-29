<?php

namespace Enna\App;

use Enna\Framework\Service as BaseService;

class Service extends BaseService
{
    public function boot()
    {
        $this->app->event->listen('HttpRun', function () {
            $this->app->middleware->add(MultiApp::class);
        });

        $this->commands([
            'build' => Command\Build::class,
            'clear' => Command\Clear::class,
        ]);

        $this->app->bind([
            'Enna\Framework\Route\Url' => Url::class
        ]);
    }
}