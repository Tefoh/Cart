<?php

namespace Tefo\Cart;

use Illuminate\Auth\Events\Logout;
use Illuminate\Session\SessionManager;
use Illuminate\Support\ServiceProvider;

class CartServiceProvider extends ServiceProvider
{
    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function register()
    {
        $this->app->bind('cart', 'Tefo\\Cart\\Cart');

        $this->app['events']->listen(Logout::class, function () {
            if ($this->app['config']->get('cart.destroy_on_logout')) {
                $this->app->make(SessionManager::class)->forget('cart');
            }
        });
    }

    /**
     *
     */
    public function boot()
    {
        //
    }
}
