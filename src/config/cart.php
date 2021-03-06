<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default tax rate
    |--------------------------------------------------------------------------
    |
    | This default tax rate will be used when you make a class implement the
    | Taxable interface and use the HasTax trait.
    |
    */

    'tax' => 21,

    /*
    |--------------------------------------------------------------------------
    | Default auth guard
    |--------------------------------------------------------------------------
    |
    | This default auth guard when user logout on this guard
    | if destroy_on_logout is true cart will be destroyed.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Destroy the cart on user logout
    |--------------------------------------------------------------------------
    |
    | When this option is set to 'true' the cart will automatically
    | destroy all cart instances when the user logs out.
    |
    */

    'destroy_on_logout' => false,

    // default number formats

    'format' => [
        'decimals' => 2,
        'decimal_point' => '.',
        'thousand_separator' => ','
    ],
];
