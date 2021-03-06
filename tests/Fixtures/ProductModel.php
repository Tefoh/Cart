<?php

namespace Tefo\Tests\Cart\Fixtures;

class ProductModel
{
    public $someValue = 'Some value';

    public function find($id): ProductModel
    {
        return $this;
    }
}
