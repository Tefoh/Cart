<?php

namespace Tefo\Cart\Facades;

use Illuminate\Support\Facades\Facade;

class Cart extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cart';
    }
}
