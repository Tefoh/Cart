<?php

namespace Tefo\Cart;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Tefo\Cart\Exceptions\InvalidItemIdException;
use Tefo\Cart\Exceptions\UnknownModelException;

class Cart
{
    public const DEFAULT_SESSION_NAME = 'tefo';
    private $session;
    private $sessionName;
    private $dispatcher;

    /**
     * Cart constructor.
     * @param SessionManager $session
     * @param Dispatcher $dispatcher
     */
    public function __construct(SessionManager $session, Dispatcher $dispatcher)
    {
        $this->session = $session;
        $this->dispatcher = $dispatcher;

        $this->session();
    }

    /**
     * @param null $name
     * @return $this
     */
    public function session($name = null): Cart
    {
        $sessionName = $name ?: self::DEFAULT_SESSION_NAME;

        $this->sessionName = sprintf('cart-%s', $sessionName);

        return $this;
    }

    /**
     * @return string|string[]
     */
    public function currentSessionName()
    {
        return str_replace('cart-', '', $this->sessionName);
    }

    /**
     * @param array $items
     * @param array $options
     * @param array $variations
     * @return Collection
     */
    public function add(array $items, array $options = [], array $variations = []): Collection
    {
        $cartItems = [];
        foreach ($items as $key => $item) {
            $option = $options[$key] ?? [];
            $variation = $variations[$key] ?? [];
            $cartItems[] = $this->addItem($item, $option, $variation);
        }

        return new Collection($cartItems);
    }

    /**
     * @param $item
     * @param array $options
     * @param array $variation
     * @return CartItem
     */
    public function addItem($item, array $options = [], array $variation = []): CartItem
    {
        $cartItem = new CartItem($item, $options, $variation);
        $content = $this->getContent();

        if (! is_array($item)) $item = $item->toArray();

        foreach ($content as $contentItem) {
            if ($this->isItemFound($contentItem, $item, $variation)) {
                $quantity = $options['quantity'] ?? 1;
                $cartItem->quantity = $contentItem->quantity + $quantity;
                $cartItem->itemId = $contentItem->itemId;
                break;
            }
        }

        $content->put($cartItem->itemId, $cartItem);

        $this->dispatcher->dispatch('cart.item.added', $cartItem);

        $this->session->put($this->sessionName, $content);

        $cartItem->setTaxRate(config('cart.tax'));

        return $cartItem;
    }

    /**
     * @param $itemId
     * @param int $quantity
     * @return mixed
     */
    public function updateQuantity($itemId, int $quantity)
    {
        $cartItem = $this->get($itemId);
        $cartItem->quantity = $quantity;

        $this->update($itemId, $cartItem, $cartItem->options, $cartItem->variation);

        return $cartItem;
    }

    /**
     * @param $itemId
     * @param $item
     * @param array $options
     * @param array $variation
     * @return mixed|void
     */
    public function update($itemId, $item, array $options = [], array $variation = [])
    {
        $cartItem = $this->get($itemId);

        if ($item instanceof HasCart) {
            $cartItem->updateFromHasCart($item, $options, $variation);
        } elseif (is_array($item)) {
            $cartItem->updateFromArray($item, $options, $variation);
        }

        $content = $this->getContent();

        $dbItem = $this->search(function ($cartItem, $dbItemId) use ($item, $variation, $itemId) {
            $cartItem = is_array($cartItem) ? $cartItem : $cartItem->toArray();
            $item = is_array($item) ? $item : $item->toArray();
            return $dbItemId !== $itemId && $cartItem['name'] == $item['name'] && $cartItem['variation'] === $variation;
        })->first();

        if ($dbItem) {
            $existingCartItem = $this->get($dbItem->itemId);
            $cartItem->setQuantity($existingCartItem->quantity + $dbItem->quantity);
            $this->remove($dbItem->itemId);
        }

        if ($cartItem->quantity <= 0) {
            $this->remove($cartItem->itemId);
            return;
        } else {
            $content->put($cartItem->itemId, $cartItem);
        }

        $this->dispatcher->dispatch('cart.item.updated', $cartItem);

        $this->session->put($this->sessionName, $content);

        return $cartItem;
    }

    /**
     * @param $itemId
     */
    public function remove($itemId)
    {
        $cartItem = $this->get($itemId);

        $content = $this->getContent();

        $content->pull($cartItem->itemId);

        $this->dispatcher->dispatch('cart.item.removed', $cartItem);

        $this->session->put($this->sessionName, $content);
    }

    /**
     * @param $itemId
     * @return mixed
     */
    public function get($itemId)
    {
        $content = $this->getContent();

        if (! $content->has($itemId)) {
            throw new InvalidItemIdException("The cart does not contain itemId {$itemId}.");
        }

        return $content->get($itemId);
    }

    /**
     * @return void
     */
    public function destroy()
    {
        $this->session->remove($this->sessionName);
    }

    public function content(): Collection
    {
        return is_null($this->session->get($this->sessionName))
            ? new Collection([])
            : $this->session->get($this->sessionName);
    }

    /**
     * @return mixed
     */
    public function count()
    {
        return $this->getContent()->sum('quantity');
    }

    /**
     * Get the total price of the items in the cart.
     *
     * @param int|null    $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function total(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null): string
    {
        $total = $this->getContent()->reduce(function ($total, CartItem $cartItem) {
            return $total + ($cartItem->quantity * $cartItem->priceTax);
        }, 0);

        return $this->formatNumber($total, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Get the total tax of the items in the cart.
     *
     * @param int|null    $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function tax(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null): string
    {
        $tax = $this->getContent()->reduce(function ($tax, CartItem $cartItem) {
            return $tax + ($cartItem->quantity * $cartItem->tax);
        }, 0);

        return $this->formatNumber($tax, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart.
     *
     * @param int|null    $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function subtotal(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null): string
    {
        $subTotal = $this->getContent()->reduce(function ($subTotal, CartItem $cartItem) {
            return $subTotal + ($cartItem->quantity * $cartItem->price);
        }, 0);

        return $this->formatNumber($subTotal, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Search the cart content for a cart item matching the given search closure.
     *
     * @param \Closure $search
     * @return Collection
     */
    public function search(\Closure $search): Collection
    {
        $content = $this->getContent();

        return $content->filter($search);
    }

    /**
     * @return Collection
     */
    protected function getContent(): Collection
    {
        return $this->session->has($this->sessionName)
            ? $this->session->get($this->sessionName)
            : new Collection;
    }

    /**
     * Associate the cart item with the given rowId with the given model.
     *
     * @param $itemId
     * @param mixed $model
     * @return void
     */
    public function associate($itemId, $model)
    {
        if(is_string($model) && ! class_exists($model)) {
            throw new UnknownModelException("The supplied model {$model} does not exist.");
        }

        $cartItem = $this->get($itemId);

        $cartItem->associate($model);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->session->put($this->sessionName, $content);
    }

    /**
     * Set the tax rate for the cart item with the given rowId.
     *
     * @param $itemId
     * @param int|float $taxRate
     * @return void
     */
    public function setTax($itemId, $taxRate)
    {
        $cartItem = $this->get($itemId);

        $cartItem->setTaxRate($taxRate);

        $content = $this->getContent();

        $content->put($cartItem->itemId, $cartItem);

        $this->session->put($this->sessionName, $content);
    }


    /**
     * Magic method to make accessing the total, tax and subtotal properties possible.
     *
     * @param string $attribute
     * @return float|null
     */
    public function __get($attribute)
    {
        if($attribute === 'total') {
            return $this->total();
        }

        if($attribute === 'tax') {
            return $this->tax();
        }

        if($attribute === 'subtotal') {
            return $this->subtotal();
        }

        return null;
    }


    /**
     * Get the formatted number.
     *
     * @param float|null  $value
     * @param int|null    $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    private function formatNumber(float $value, int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null): string
    {
        if(is_null($decimals)) {
            $decimals = config('cart.format.decimals', 2);
        }
        if(is_null($decimalPoint)) {
            $decimalPoint = config('cart.format.decimal_point', '.');
        }
        if(is_null($thousandSeparator)) {
            $thousandSeparator = config('cart.format.thousand_separator', ',');
        }

        return number_format($value, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * @param $cartItem
     * @param $item
     * @param array $variation
     * @return bool
     */
    private function isItemFound($cartItem, $item, array $variation): bool
    {
        $item = is_array($item) ? $item : $item->toArray();
        return $cartItem->id === $item['id'] && $variation === $cartItem->variation;
    }
}
