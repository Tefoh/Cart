<?php

namespace Tefo\Tests\Cart\Cart;

use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Application;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\TestCase;
use Tefo\Cart\Cart;
use Tefo\Cart\CartItem;
use Tefo\Cart\CartServiceProvider;
use Tefo\Cart\Exceptions\InvalidItemIdException;
use Tefo\Cart\Exceptions\UnknownModelException;
use Tefo\Tests\Cart\CartAssertions;
use Tefo\Tests\Cart\Fixtures\Product;
use Tefo\Tests\Cart\Fixtures\ProductModel;

class CartTest extends TestCase
{
    use CartAssertions;

    /**
     * Set the package service provider.
     *
     * @param Application $app
     * @return array
     */
    protected function getPackageProviders(Application $app): array
    {
        return [CartServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param Application $app
     * @return void
     */
    protected function getEnvironmentSetUp(Application $app)
    {
        $app['config']->set('session.driver', 'array');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    /** @test */
    public function it_has_a_default_instance()
    {
        $cart = $this->getCart();

        $this->assertEquals(Cart::DEFAULT_SESSION_NAME, $cart->currentSessionName());
    }

    /** @test */
    public function it_can_have_multiple_instances()
    {
        $cart = $this->getCart();

        $cart->addItem(new Product(1, 'First item'));

        $cart->session('wishlist')->addItem(new Product(2, 'Second item'));

        $this->assertItemsInCart(1, $cart->session(Cart::DEFAULT_SESSION_NAME));
        $this->assertItemsInCart(1, $cart->session('wishlist'));
    }

    /** @test */
    public function it_can_add_an_item()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->addItem(new Product);

        $this->assertEquals(1, $cart->count());

        Event::assertDispatched('cart.item.added');
    }

    /** @test */
    public function it_will_return_the_cartitem_of_the_added_item()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->addItem(new Product);

        $this->assertInstanceOf(CartItem::class, $cartItem);
        $this->assertEquals($cartItem->itemId, $cartItem->itemId);

        Event::assertDispatched('cart.item.added');
    }

    /** @test */
    public function it_can_add_multiple_buyable_items_at_once()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add([new Product(1), new Product(2)]);

        $this->assertEquals(2, $cart->count());

        Event::assertDispatched('cart.item.added');
    }

    /** @test */
    public function it_will_return_a_collection_of_cart_items_when_you_add_multiple_items_at_once()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItems = $cart->add([new Product(1), new Product(2)]);

        $this->assertTrue($cartItems instanceof Collection);
        $this->assertCount(2, $cartItems);
        $this->assertContainsOnlyInstancesOf(CartItem::class, $cartItems);

        Event::assertDispatched('cart.item.added');
    }

    /** @test */
    public function it_can_add_an_item_from_attributes()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->addItem(['id' => 1, 'name' => 'Test item','price' => 10.00], ['quantity' => 1]);

        $this->assertEquals(1, $cart->count());

        Event::assertDispatched('cart.item.added');
    }

    /** @test */
    public function it_can_add_multiple_array_items_at_once()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add([
            ['id' => 1, 'name' => 'Test item 1', 'price' => 10.00],
            ['id' => 2, 'name' => 'Test item 2', 'price' => 10.00]
        ], [
            ['quantity' => 1],
            ['quantity' => 2]
        ]);

        $this->assertEquals(3, $cart->count());

        Event::assertDispatched('cart.item.added');
    }

    /** @test */
    public function it_can_add_an_item_with_variations()
    {
        Event::fake();

        $cart = $this->getCart();

        $variation = ['size' => 'XL', 'color' => 'red'];

        $item = $cart->addItem(new Product, [ 'id' => 1], $variation);

        $cartItem = $cart->get($item->itemId);

        $this->assertInstanceOf(CartItem::class, $cartItem);
        $this->assertEquals('XL', $cartItem->variation['size']);
        $this->assertEquals('red', $cartItem->variation['color']);

        Event::assertDispatched('cart.item.added');
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Please pass a valid identifier.
     */
    public function it_will_validate_the_identifier()
    {
        $cart = $this->getCart();
        $this->expectException(\InvalidArgumentException::class);

        $cart->addItem(['id' => null]);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Please pass a valid name.
     */
    public function it_will_validate_the_name()
    {
        $cart = $this->getCart();
        $this->expectException(\InvalidArgumentException::class);

        $cart->addItem(['id' => 1, 'name' => null]);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Please pass a valid quantity.
     */
    public function it_will_validate_the_quantity()
    {
        $cart = $this->getCart();
        $this->expectException(\InvalidArgumentException::class);

        $cart->addItem(['id' => 1, 'name' => 'Some title', 'price' => 10.00], ['quantity' => 'invalid']);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Please pass a valid price.
     */
    public function it_will_validate_the_price()
    {
        $cart = $this->getCart();
        $this->expectException(\InvalidArgumentException::class);

        $cart->addItem(['id' => 1, 'name' => 'Some title', 'price' => 'invalid'], ['quantity' => 1]);
    }

    /** @test */
    public function it_will_update_the_cart_if_the_item_already_exists_in_the_cart()
    {
        $cart = $this->getCart();

        $item = new Product;

        $cart->addItem($item);
        $cart->addItem($item);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    /** @test */
    public function it_will_keep_updating_the_quantity_when_an_item_is_added_multiple_times()
    {
        $cart = $this->getCart();

        $item = new Product;

        $cart->addItem($item);
        $cart->addItem($item);
        $cart->addItem($item);

        $this->assertItemsInCart(3, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    /** @test */
    public function it_can_update_the_quantity_of_an_existing_item_in_the_cart()
    {
        Event::fake();

        $cart = $this->getCart();

        $item = $cart->addItem(new Product);

        $cart->updateQuantity($item->itemId, 2);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);

        Event::assertDispatched('cart.item.updated');
    }

    /** @test */
    public function it_can_update_an_existing_item_in_the_cart_from_a_hascart()
    {
        Event::fake();

        $cart = $this->getCart();

        $item = $cart->addItem(new Product);

        $cart->update($item->itemId, new Product(1, 'Different description'));
        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('Different description', $cart->get($item->itemId)->name);

        Event::assertDispatched('cart.item.updated');
    }

    /** @test */
    public function it_can_update_an_existing_item_in_the_cart_from_an_array()
    {
        Event::fake();

        $cart = $this->getCart();

        $item = $cart->addItem(new Product);

        $cart->update($item->itemId, ['name' => 'Different description']);

        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('Different description', $cart->get($item->itemId)->name);

        Event::assertDispatched('cart.item.updated');
    }

    /**
     * @test
     * @expectedException \Gloudemans\Shoppingcart\Exceptions\InvalidRowIDException
     */
    public function it_will_throw_an_exception_if_a_item_id_was_not_found()
    {
        $cart = $this->getCart();
        $this->expectException(InvalidItemIdException::class);

        $cart->addItem(new Product);

        $cart->update('none-existing-item-id', new Product(1, 'Different description'));
    }

    /** @test */
    public function it_will_add_the_item_to_an_existing_row_if_the_options_changed_to_an_existing_rowid()
    {
        $cart = $this->getCart();

        $product = new Product;
        $cart->addItem(new Product, ['quantity' => 1], ['color' => 'red']);
        $item = $cart->addItem($product, ['quantity' => 1], ['color' => 'blue']);

        $cart->update($item->itemId, $product, [], ['color' => 'red']);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    /** @test */
    public function it_can_remove_an_item_from_the_cart()
    {
        Event::fake();
        $cart = $this->getCart();

        $item = $cart->addItem(new Product);

        $cart->remove($item->itemId);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.item.removed');
    }

    /** @test */
    public function it_will_remove_the_item_if_its_quantity_was_set_to_zero()
    {
        Event::fake();

        $cart = $this->getCart();

        $item = $cart->addItem(new Product);

        $cart->updateQuantity($item->itemId, 0);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.item.removed');
    }

    /** @test */
    public function it_will_remove_the_item_if_its_quantity_was_set_negative()
    {
        Event::fake();

        $cart = $this->getCart();

        $item = $cart->addItem(new Product);

        $cart->updateQuantity($item->itemId, -1);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.item.removed');
    }

    /** @test */
    public function it_can_get_an_item_from_the_cart_by_its_rowid()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product);

        $cartItem = $cart->get($item->itemId);

        $this->assertInstanceOf(CartItem::class, $cartItem);
    }

    /** @test */
    public function it_can_get_the_content_of_the_cart()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1));
        $secondItem = $cart->addItem(new Product(2));

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertCount(2, $content);
    }

    /** @test */
    public function it_will_return_an_empty_collection_if_the_cart_is_empty()
    {
        $cart = $this->getCart();

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertCount(0, $content);
    }

    /** @test */
    public function it_will_include_the_tax_and_subtotal_when_converted_to_an_array()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1));
        $secondItem = $cart->addItem(new Product(2));

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertEquals([
            $item->itemId => [
                'itemId' => $item->itemId,
                'id' => 1,
                'name' => 'Item name',
                'quantity' => 1,
                'price' => 10.00,
                'tax' => 2.10,
                'subtotal' => 10.0,
                'options' => [],
                'variation' => []
            ],
            $secondItem->itemId => [
                'itemId' => $secondItem->itemId,
                'id' => 2,
                'name' => 'Item name',
                'quantity' => 1,
                'price' => 10.00,
                'tax' => 2.10,
                'subtotal' => 10.0,
                'options' => [],
                'variation' => []
            ]
        ], $content->map->toArray()->toArray());
    }

    /** @test */
    public function it_can_destroy_a_cart()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product);

        $this->assertItemsInCart(1, $cart);

        $cart->destroy();

        $this->assertItemsInCart(0, $cart);
    }

    /** @test */
    public function it_can_get_the_total_price_of_the_cart_content()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'First item', 10.00));
        $item = $cart->addItem(new Product(2, 'Second item', 25.00), ['quantity' => 2]);

        $this->assertItemsInCart(3, $cart);
        $this->assertEquals(60.00, $cart->subtotal());
    }

    /** @test */
    public function it_can_return_a_formatted_total()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'First item', 1000.00));
        $item = $cart->addItem(new Product(2, 'Second item', 2500.00), ['quantity' => 2]);

        $this->assertItemsInCart(3, $cart);
        $this->assertEquals('6.000,00', $cart->subtotal(2, ',', '.'));
    }

    /** @test */
    public function it_can_search_the_cart_for_a_specific_item()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'Some item'));
        $item = $cart->addItem(new Product(2, 'Another item'));

        $cartItem = $cart->search(function ($cartItem, $itemId) {
            return $cartItem->name == 'Some item';
        });

        $this->assertInstanceOf(Collection::class, $cartItem);
        $this->assertCount(1, $cartItem);
        $this->assertInstanceOf(CartItem::class, $cartItem->first());
        $this->assertEquals(1, $cartItem->first()->id);
    }

    /** @test */
    public function it_can_search_the_cart_for_multiple_items()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'Some item'));
        $item = $cart->addItem(new Product(2, 'Some item'));
        $item = $cart->addItem(new Product(3, 'Another item'));

        $cartItem = $cart->search(function ($cartItem, $itemId) {
            return $cartItem->name == 'Some item';
        });

        $this->assertInstanceOf(Collection::class, $cartItem);
    }

    /** @test */
    public function it_can_search_the_cart_for_a_specific_item_with_options()
    {
        $cart = $this->getCart();

        $cart->addItem(new Product(1, 'Some item'), ['quantity' => 1], ['color' => 'red']);
        $cart->addItem(new Product(2, 'Another item'), ['quantity' => 1], ['color' => 'blue']);

        $cartItem = $cart->search(function ($cartItem) {
            return $cartItem->variation['color'] == 'red';
        });

        $this->assertInstanceOf(Collection::class, $cartItem);
        $this->assertCount(1, $cartItem);
        $this->assertInstanceOf(CartItem::class, $cartItem->first());
        $this->assertEquals(1, $cartItem->first()->id);
    }

    /** @test */
    public function it_will_associate_the_cart_item_with_a_model_when_you_add_a_buyable()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product);

        $cartItem = $cart->get($item->itemId);

        $this->assertTrue($cartItem->associatedModel === get_class(new Product));
    }

    /** @test */
    public function it_can_associate_the_cart_item_with_a_model()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(['id' => 1,'name' => 'Test item', 'price' => 10.00], ['quantity' => 1]);

        $cart->associate($item->itemId, new Product);

        $cartItem = $cart->get($item->itemId);

        $this->assertEquals(get_class(new Product), $cartItem->associatedModel);
    }

    /**
     * @test
     * @expectedException \Tefo\Cart\Exceptions\UnknownModelException
     * @expectedExceptionMessage The supplied model SomeModel does not exist.
     */
    public function it_will_throw_an_exception_when_a_non_existing_model_is_being_associated()
    {
        $cart = $this->getCart();
        $this->expectException(UnknownModelException::class);

        $item = $cart->addItem(['id' => 1, 'name' => 'Test item', 'price' => 10.00], ['quantity' => 1]);

        $cart->associate($item->itemId, 'SomeModel');
    }

    /** @test */
    public function it_can_get_the_associated_model_of_a_cart_item()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(['id' => 1, 'name' => 'Test item', 'price' => 10.00], ['quantity' => 1]);

        $cart->associate($item->itemId, new ProductModel);

        $cartItem = $cart->get($item->itemId);

        $this->assertInstanceOf(ProductModel::class, $cartItem->model);
        $this->assertEquals('Some value', $cartItem->model->someValue);
    }

    /** @test */
    public function it_can_calculate_the_subtotal_of_a_cart_item()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'Some title', 9.99), ['quantity' => 3]);

        $cartItem = $cart->get($item->itemId);

        $this->assertEquals(29.97, $cartItem->subtotal);
    }

    /** @test */
    public function it_can_return_a_formatted_subtotal()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'Some title', 500), ['quantity' => 3]);

        $cartItem = $cart->get($item->itemId);

        $this->assertEquals('1.500,00', $cartItem->subtotal(2, ',', '.'));
    }

    /** @test */
    public function it_can_calculate_tax_based_on_the_default_tax_rate_in_the_config()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'Some title', 10.00), ['quantity' => 1]);

        $cartItem = $cart->get($item->itemId);

        $this->assertEquals(2.10, $cartItem->tax);
    }

    /** @test */
    public function it_can_calculate_tax_based_on_the_specified_tax()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'Some title', 10.00), ['quantity' => 1]);

        $cart->setTax($item->itemId, 19);

        $cartItem = $cart->get($item->itemId);

        $this->assertEquals(1.90, $cartItem->tax);
    }

    /** @test */
    public function it_can_return_the_calculated_tax_formatted()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'Some title', 10000.00), ['quantity' => 1]);

        $cartItem = $cart->get($item->itemId);

        $this->assertEquals('2.100,00', $cartItem->tax(2, ',', '.'));
    }

    /** @test */
    public function it_can_calculate_the_total_tax_for_all_cart_items()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'Some title', 10.00), ['quantity' => 1]);
        $item = $cart->addItem(new Product(2, 'Some title', 20.00), ['quantity' => 2]);

        $this->assertEquals(10.50, $cart->tax);
    }

    /** @test */
    public function it_can_return_formatted_total_tax()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'Some title', 1000.00), ['quantity' => 1]);
        $item = $cart->addItem(new Product(2, 'Some title', 2000.00), ['quantity' => 2]);

        $this->assertEquals('1.050,00', $cart->tax(2, ',', '.'));
    }

    /** @test */
    public function it_can_return_the_subtotal()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'Some title', 10.00), ['quantity' => 1]);
        $item = $cart->addItem(new Product(2, 'Some title', 20.00), ['quantity' => 2]);

        $this->assertEquals(50.00, $cart->subtotal);
    }

    /** @test */
    public function it_can_return_formatted_subtotal()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'Some title', 1000.00), ['quantity' => 1]);
        $item = $cart->addItem(new Product(2, 'Some title', 2000.00), ['quantity' => 2]);

        $this->assertEquals('5000,00', $cart->subtotal(2, ',', ''));
    }

    /** @test */
    public function it_can_return_cart_formatted_numbers_by_config_values()
    {
        $this->setConfigFormat(2, ',', '');

        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'Some title', 1000.00), ['quantity' => 1]);
        $item = $cart->addItem(new Product(2, 'Some title', 2000.00), ['quantity' => 2]);

        $this->assertEquals('5000,00', $cart->subtotal());
        $this->assertEquals('1050,00', $cart->tax());
        $this->assertEquals('6050,00', $cart->total());

        $this->assertEquals('5000,00', $cart->subtotal);
        $this->assertEquals('1050,00', $cart->tax);
        $this->assertEquals('6050,00', $cart->total);
    }

    /** @test */
    public function it_can_return_cartItem_formatted_numbers_by_config_values()
    {
        $this->setConfigFormat(2, ',', '');

        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'Some title', 2000.00), ['quantity' => 2]);

        $cartItem = $cart->get($item->itemId);

        $this->assertEquals('2000,00', $cartItem->price());
        $this->assertEquals('2420,00', $cartItem->priceTax());
        $this->assertEquals('4000,00', $cartItem->subtotal());
        $this->assertEquals('4840,00', $cartItem->total());
        $this->assertEquals('420,00', $cartItem->tax());
        $this->assertEquals('840,00', $cartItem->taxTotal());
    }

    // TODO
    /** @test */
    public function it_can_calculate_all_values()
    {
        $cart = $this->getCart();

        $item = $cart->addItem(new Product(1, 'First item', 10.00), ['quantity' => 2]);

        $cartItem = $cart->get($item->itemId);

        $cart->setTax($item->itemId, 19);

        $this->assertEquals(10.00, $cartItem->price(2));
        $this->assertEquals(11.90, $cartItem->priceTax(2));
        $this->assertEquals(20.00, $cartItem->subtotal(2));
        $this->assertEquals(23.80, $cartItem->total(2));
        $this->assertEquals(1.90, $cartItem->tax(2));
        $this->assertEquals(3.80, $cartItem->taxTotal(2));

        $this->assertEquals(20.00, $cart->subtotal(2));
        $this->assertEquals(23.80, $cart->total(2));
        $this->assertEquals(3.80, $cart->tax(2));
    }

    /** @test */
    public function it_will_destroy_the_cart_when_the_user_logs_out_and_the_config_setting_was_set_to_true()
    {
        $this->app['config']->set('cart.destroy_on_logout', true);

        $this->app->instance(SessionManager::class, Mockery::mock(SessionManager::class, function ($mock) {
            $mock->shouldReceive('forget')->once()->with('cart');
        }));

        $user = Mockery::mock(Authenticatable::class);

        event(new Logout(config('cart.guard', 'web'), $user));
    }

    /**
     * Get an instance of the cart.
     *
     * @return Cart
     */
    private function getCart(): Cart
    {
        $session = $this->app->make('session');
        $events = $this->app->make('events');

        return new Cart($session, $events);
    }

    /**
     * Set the config number format.
     *
     * @param int $decimals
     * @param string $decimalPoint
     * @param string $thousandSeparator
     */
    private function setConfigFormat(int $decimals, string $decimalPoint, string $thousandSeparator)
    {
        $this->app['config']->set('cart.format.decimals', $decimals);
        $this->app['config']->set('cart.format.decimal_point', $decimalPoint);
        $this->app['config']->set('cart.format.thousand_separator', $thousandSeparator);
    }
}
