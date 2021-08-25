<?php

namespace Hawk\LighthouseExtended;

use Hawk\LighthouseExtended\Support\Mutations\Mutator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class LighthouseExtendedServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Event::listen(RegisterDirectiveNamespaces::class, function () {
            return ['Hawk\\LighthouseExtended\\Schema\\Directives\\Fields'];
        });

        $this->app->resolving(Mutator::class, function (Mutator $mutator) {
            $mutator->setJson($mutator->getInput()->toArray());
        });
    }
}
