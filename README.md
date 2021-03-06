## Laravel cart
[![Build Status](https://travis-ci.org/Tefoh/Cart.svg?branch=master)](https://travis-ci.org/Tefoh/Cart)
[![Total Downloads](https://poser.pugx.org/tefo/cart/downloads.png)](https://packagist.org/packages/tefo/cart)
[![Latest Stable Version](https://poser.pugx.org/tefo/cart/v/stable)](https://packagist.org/packages/tefo/cart)
[![License](https://poser.pugx.org/tefo/cart/license)](https://packagist.org/packages/tefo/cart)

A simple shopping cart implementation for Laravel.

## Installation

Install the package through [Composer](http://getcomposer.org/).

Run the Composer require command from the Terminal:

    composer require tefo/cart

Now you're ready to start using the cart in your application.

## Overview
Look at one of the following topics to learn more about cart

* [Usage](#usage)
* [Collections](#collections)
* [Instances](#instances)
* [Models](#models)
* [Exceptions](#exceptions)
* [Events](#events)
* [Example](#example)

## Usage

first implement your models want to add to your cart `HasCart` interface:
```php
use Tefo\Cart\HasCart;
use Tefo\Cart\InteractsWithCard;

class Product extends Model implements HasCart
{
    use InteractsWithCard;
    // ...
}
```

Now you can use the following methods of cart to use:

### Cart::addItem()

For add one item to cart use this method. for first parameter it excepts a product that implements `HasCart` or an array.

```php
Cart::addItem($product);
```

this method has optional parameters, for second parameter you can set products options like quantity. if quantity not set by default it will be 1. for third optional option you can set product variation like `['color' => 'red']`.

```php
Cart::addItem($product, ['quantity' => 2], ['color' => 'red']);
```

### Cart::add()

Adding multiple items to the cart is really simple, you just use the `add()` method, which accepts a variety of parameters.

for first parameter excepts an array of products info, every product can be an eloquent model or an array.

```php
Cart::add([$product_1, $product_2]);
```

for second array it excepts an array options for each product like product quantity but its optional. last parameter that its optional too it excepts an array each product variations.

```php
Cart::add([$product_1, $product_2], [['quantity' => 1], ['quantity' => 2]], [$product_1_Variation, $product_2_Variation]);
```

**The `add()` method will return a collection CartItem instance of the item you just added to the cart.**

### Cart::update()

To update an item in the cart, you'll first need the itemId of the item.
Next you can use the `update()` method to update it.

If you want to update more attributes of the item, you can either pass the update method an array or a `HasCart` as the second parameter. This way you can update all information of the item with the given itemId.

```php
$itemId = '92e1b081-17b9-4f7a-8f91-91da9dbcd6ca'; // its uuid

Cart::update($itemId, $updatedProduct); // Will update the id, name and price

Cart::update($itemId, ['name' => 'Product 1']); // Will update the name
```

If you simply want to update the quantity, you'll pass the update method the itemId and the new quantity:

```php
$itemId = '92e1b081-17b9-4f7a-8f91-91da9dbcd6ca';

Cart::updateQuantity($itemId, 2); // Will update the quantity
```


### Cart::remove()

To remove an item for the cart, you'll again need the itemId. This itemId you simply pass to the `remove()` method and it will remove the item from the cart.

```php
$itemId = '92e1b081-17b9-4f7a-8f91-91da9dbcd6ca';

Cart::remove($itemId);
```

### Cart::get()

If you want to get an item from the cart using its itemId, you can simply call the `get()` method on the cart and pass it the itemId.

```php
$itemId = '92e1b081-17b9-4f7a-8f91-91da9dbcd6ca';

Cart::get($itemId);
```

### Cart::content()

Of course you also want to get the carts content. This is where you'll use the `content` method. This method will return a Collection of CartItems which you can iterate over and show the content to your customers.

```php
Cart::content();
```

This method will return the content of the current cart instance, if you want the content of another instance, simply chain the calls.

```php
Cart::session('wishlist')->content();
```

### Cart::destroy()

If you want to completely remove the content of a cart, you can call the destroy method on the cart. This will remove all CartItems from the cart for the current cart instance.

```php
Cart::destroy();
```

### Cart::total()

The `total()` method can be used to get the calculated total of all items in the cart, given there price and quantity.

```php
Cart::total();
```

The method will automatically format the result, which you can tweak using the three optional parameters

```php
Cart::total($decimals, $decimalSeperator, $thousandSeperator);
```

You can set the default number format in the config file.

**If you're not using the Facade, but use dependency injection in your (for instance) Controller, you can also simply get the total property `$cart->total`**

### Cart::tax()

The `tax()` method can be used to get the calculated amount of tax for all items in the cart, given there price and quantity.

```php
Cart::tax();
```

The method will automatically format the result, which you can tweak using the three optional parameters

```php
Cart::tax($decimals, $decimalSeperator, $thousandSeperator);
```

You can set the default number format in the config file.

**If you're not using the Facade, but use dependency injection in your (for instance) Controller, you can also simply get the tax property `$cart->tax`**

### Cart::subtotal()

The `subtotal()` method can be used to get the total of all items in the cart, minus the total amount of tax.

```php
Cart::subtotal();
```

The method will automatically format the result, which you can tweak using the three optional parameters

```php
Cart::subtotal($decimals, $decimalSeperator, $thousandSeperator);
```

You can set the default number format in the config file.

**If you're not using the Facade, but use dependency injection in your (for instance) Controller, you can also simply get the subtotal property `$cart->subtotal`**

### Cart::count()

If you want to know how many items there are in your cart, you can use the `count()` method. This method will return the total number of items in the cart. So if you've added 2 books and 1 shirt, it will return 3 items.

```php
Cart::count();
```

### Cart::search()

To find an item in the cart, you can use the `search()` method.

Behind the scenes, the method simply uses the filter method of the Laravel Collection class. This means you must pass it a Closure in which you'll specify you search terms.

If you for instance want to find all items with an id of 1:

```php
$cart->search(function ($cartItem, $itemId) {
	return $cartItem->id === 1;
});
```

As you can see the Closure will receive two parameters. The first is the CartItem to perform the check against. The second parameter is the itemId of this CartItem.

**The method will return a Collection containing all CartItems that where found**

This way of searching gives you total control over the search process and gives you the ability to create very precise and specific searches.

## Collections

On multiple instances the Cart will return to you a Collection. This is just a simple Laravel Collection, so all methods you can call on a Laravel Collection are also available on the result.

As an example, you can quickly get the number of unique products in a cart:

```php
Cart::content()->count();
```

Or you can group the content by the id of the products:

```php
Cart::content()->groupBy('id');
```

## Instances

The packages supports multiple instances of the cart. The way this works is like this:

You can set the current instance of the cart by calling `Cart::session('newInstance')`. From this moment, the active instance of the cart will be `newInstance`, so when you add, remove or get the content of the cart, you're work with the `newInstance` instance of the cart.
If you want to switch instances, you just call `Cart::session('otherInstance')` again, and you're working with the `otherInstance` again.

So a little example:

```php
Cart::session('shopping')->addItem(['id' => 1, 'name' => 'Product 1', 'price' => 9.99], ['quantity' => 1]);

// Get the content of the 'shopping' cart
Cart::content();

Cart::session('wishlist')->addItem($product_2, ['quantity' => 2], ['size' => 'medium']);

// Get the content of the 'wishlist' cart
Cart::content();

// If you want to get the content of the 'shopping' cart again
Cart::session('shopping')->content();

// And the count of the 'wishlist' cart again
Cart::session('wishlist')->count();
```

**N.B. Keep in mind that the cart stays in the last set instance for as long as you don't set a different one during script execution.**

**N.B.2 The default cart instance is called `default`, so when you're not using instances,`Cart::content();` is the same as `Cart::instance('default')->content()`.**

## Models

Because it can be very convenient to be able to directly access a model from a CartItem is it possible to associate a model with the items in the cart. Let's say you have a `Product` model in your application. With the `associate()` method, you can tell the cart that an item in the cart, is associated to the `Product` model.

That way you can access your model right from the `CartItem`!

The model can be accessed via the `model` property on the CartItem.

**If your model implements the `HasCart` interface and you used your model to add the item to the cart, it will associate automatically.**

Here is an example:

```php

// First we'll add the item to the cart.
$cartItem = Cart::addItem(['id' => 1, 'name' => 'Product 1', 'price' => 9.99], ['quantity' => 2], ['size' => 'large']);

// Next we associate a model with the item.
Cart::associate($cartItem->itemId, 'Product');

// Or even easier, call the associate method on the CartItem!
$cartItem->associate('Product');

// You can even make it a one-liner
Cart::addItem(['id' => 2, 'name' => 'Product 2', 'price' => 9.99], ['quantity' => 1], ['size' => 'large'])->associate('Product');

// Now, when iterating over the content of the cart, you can access the model.
foreach(Cart::content() as $row) {
	echo 'You have ' . $row->quantity . ' items of ' . $row->model->name . ' with description: "' . $row->model->description . '" in your cart.';
}
```

### Configuration
To publish the `config` file.

    php artisan vendor:publish --provider="Gloudemans\Shoppingcart\ShoppingcartServiceProvider" --tag="config"

This will give you a `cart.php` config file in which you can make the changes.

## Exceptions

The Cart package will throw exceptions if something goes wrong. This way it's easier to debug your code using the Cart package or to handle the error based on the type of exceptions. The Cart packages can throw the following exceptions:

| Exception                    | Reason                                                                             |
| ---------------------------- | ---------------------------------------------------------------------------------- |
| *InvalidRowIDException*      | When the itemId that got passed doesn't exists in the current cart instance         |
| *UnknownModelException*      | When you try to associate an none existing model to a CartItem.                    |

## Events

The cart also has events build in. There are five events available for you to listen for.

| Event              | Fired                                    | Parameter                        |
| ------------------ | ---------------------------------------- | -------------------------------- |
| cart.item.added    | When an item was added to the cart.      | The `CartItem` that was added.   |
| cart.item.updated  | When an item in the cart was updated.    | The `CartItem` that was updated. |
| cart.item.removed  | When an item is removed from the cart.   | The `CartItem` that was removed. |

## Example

Below is a little example of how to list the cart content in a table:

```html

// Add some items in your Controller.
Cart::addItem(['id' => 1, 'name' => 'Product 1', 'price' => 9.99], ['quantity' => 1]);
Cart::addItem(['id' => 2, 'name' => 'Product 2', 'price' => 5.95], ['quantity' => 2], ['size' => 'large']);

// Display the content in a View.
<table>
   	<thead>
       	<tr>
           	<th>Product</th>
           	<th>Quantity</th>
           	<th>Price</th>
           	<th>Subtotal</th>
       	</tr>
   	</thead>

   	<tbody>
   		@foreach(Cart::content() as $row)
       		<tr>
           		<td>
               		<p><strong>{{ $row->name }}</strong></p>
               		<p>@if($row->variation->has('size')) {{ $row->variation->size }} @endif</p>
           		</td>
           		<td><input type="text" value="{{ $row->quantity }}"></td>
           		<td>${{ $row->price }}</td>
           		<td>${{ $row->total }}</td>
       		</tr>
	   	@endforeach
   	</tbody>
   	<tfoot>
   		<tr>
   			<td colspan="2">&nbsp;</td>
   			<td>Subtotal</td>
   			<td>{{ Cart::subtotal() }}</td>
   		</tr>
   		<tr>
   			<td colspan="2">&nbsp;</td>
   			<td>Tax</td>
   			<td>{{ Cart::tax() }}</td>
   		</tr>
   		<tr>
   			<td colspan="2">&nbsp;</td>
   			<td>Total</td>
   			<td>{{ Cart::total() }}</td>
   		</tr>
   	</tfoot>
</table>
```
