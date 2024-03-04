<?php

namespace Enna\App;

use Enna\Framework\Service as BaseService;

class Service extends BaseService
{
    public function boot()
    {
        $this->app->event->trigger('HttpRun', function () {
            $this->app->middleware->add(MultiApp::class);
        });

        $this->commands([

        ]);

        $this->app->bind([

        ]);
    }
}