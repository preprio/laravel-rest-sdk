<?php

namespace Preprio;

use Illuminate\Support\Facades\Facade;

class PreprFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'prepr';
    }
}
