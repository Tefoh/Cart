<?php

namespace Tefo\Tests\Cart\Cart;

use Orchestra\Testbench\TestCase;
use Tefo\Cart\CartItem;
use Tefo\Cart\CartServiceProvider;

class CartItemTest extends TestCase
{

    /**
     * Set the package service provider.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [CartServiceProvider::class];
    }

    /** @test */
    public function it_can_be_cast_to_an_array()
    {
        $cartItem = new CartItem(['id' => 1, 'name' => 'Some item', 'price' => 10.00], [], ['size' => 'XL', 'color' => 'red']);
        $cartItem->setQuantity(2);

        $this->assertEquals([
            'id' => 1,
            'name' => 'Some item',
            'price' => 10.00,
            'itemId' => $cartItem->itemId,
            'quantity' => 2,
            'options' => [],
            'variation' => [
                'size' => 'XL',
                'color' => 'red'
            ],
            'tax' => 0.0,
            'subtotal' => 20.00,
        ], $cartItem->toArray());
    }

    /** @test */
    public function it_can_be_cast_to_json()
    {
        $cartItem = new CartItem(['id' => 1, 'name' => 'Some item', 'price' => 10.00], [], ['size' => 'XL', 'color' => 'red']);
        $cartItem->setQuantity(2);

        $this->assertJson($cartItem->toJson());

        $json = '{"itemId":"' . $cartItem->itemId . '","id":1,"name":"Some item","quantity":2,"price":10,"options":[],"variation":{"size":"XL","color":"red"},"tax":0,"subtotal":20}';

        $this->assertEquals($json, $cartItem->toJson());
    }
}
