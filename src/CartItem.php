<?php

namespace Tefo\Cart;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CartItem implements Arrayable, Jsonable
{
    /**
     * @var string
     */
    public $itemId;
    /**
     * @var mixed
     */
    public $id;
    /**
     * @var int|string
     */
    public $quantity;
    /**
     * @var mixed
     */
    public $name;
    /**
     * @var float
     */
    public $price;
    /**
     * @var array
     */
    public $options;
    /**
     * @var array
     */
    public $variation;
    /**
     * @var false|string
     */
    private $associatedModel;
    /**
     * @var
     */
    private $taxRate;

    /**
     * CartItem constructor.
     * @param $item
     * @param array $options
     * @param array $variation
     */
    public function __construct($item, array $options = [], array $variation = [])
    {
        if ($item instanceof HasCart) {
            $this->associatedModel = get_class($item);
        }

        if (! is_array($item)) {
            $item = $item->toArray();
        }

        if(empty($item['id'])) {
            throw new \InvalidArgumentException('Please pass a valid identifier.');
        }
        if(empty($item['name'])) {
            throw new \InvalidArgumentException('Please pass a valid name.');
        }
        if(strlen($item['price']) < 0 || ! is_numeric($item['price'])) {
            throw new \InvalidArgumentException('Please pass a valid price.');
        }

        if(isset($options['quantity']) && ! is_numeric($options['quantity'])) {
            throw new \InvalidArgumentException('Please pass a valid quantity.');
        }

        $this->itemId = Str::orderedUuid()->toString();
        $this->id = $item['id'];
        $this->name = $item['name'];
        $this->price = floatval($item['price']);
        $this->quantity = isset($options['quantity']) ? $options['quantity'] : 1;
        $this->options = $options;
        $this->variation = $variation;
    }

    /**
     * Returns the formatted price without TAX.
     *
     * @param int|null    $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function price($decimals = null, $decimalPoint = null, $thousandSeparator = null): string
    {
        return $this->formatNumber($this->price, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted price with TAX.
     *
     * @param int|null    $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function priceTax(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null): string
    {
        return $this->formatNumber($this->priceTax, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted subtotal.
     * Subtotal is price for whole CartItem without TAX
     *
     * @param int|null    $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function subtotal(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null): string
    {
        return $this->formatNumber($this->subtotal, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted total.
     * Total is price for whole CartItem with TAX
     *
     * @param int|null    $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function total(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null): string
    {
        return $this->formatNumber($this->total, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted tax.
     *
     * @param int|null    $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function tax(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null): string
    {
        return $this->formatNumber($this->tax, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted tax.
     *
     * @param int|null    $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function taxTotal(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null): string
    {
        return $this->formatNumber($this->taxTotal, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Set the tax rate.
     *
     * @param $taxRate
     * @return CartItem
     */
    public function setTaxRate($taxRate): CartItem
    {
        $this->taxRate = $taxRate;

        return $this;
    }

    /**
     * Get an attribute from the cart item or get the associated model.
     *
     * @param string $attribute
     * @return mixed
     */
    public function __get(string $attribute)
    {
        if(property_exists($this, $attribute)) {
            return $this->{$attribute};
        }

        if($attribute === 'priceTax') {
            return $this->price + $this->tax;
        }

        if($attribute === 'subtotal') {
            return $this->quantity * $this->price;
        }

        if($attribute === 'total') {
            return $this->quantity * ($this->priceTax);
        }

        if($attribute === 'tax') {
            return $this->price * ($this->taxRate / 100);
        }

        if($attribute === 'taxTotal') {
            return $this->tax * $this->quantity;
        }

        if($attribute === 'model' && isset($this->associatedModel)) {
            return with(new $this->associatedModel)->find($this->id);
        }

        return null;
    }


    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'itemId'   => $this->itemId,
            'id'       => $this->id,
            'name'     => $this->name,
            'quantity' => $this->quantity,
            'price'    => $this->price,
            'options'  => $this->options,
            'variation'=> $this->variation,
            'tax'      => $this->tax,
            'subtotal' => $this->subtotal
        ];
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Set the quantity for this cart item.
     *
     * @param int|float $quantity
     */
    public function setQuantity($quantity)
    {
        if(empty($quantity) || ! is_numeric($quantity))
            throw new \InvalidArgumentException('Please pass a valid quantity.');

        $this->quantity = $quantity;
    }

    /**
     * Associate the cart item with the given model.
     *
     * @param mixed $model
     * @return CartItem
     */
    public function associate($model): CartItem
    {
        $this->associatedModel = is_string($model) ? $model : get_class($model);

        return $this;
    }

    /**
     * @param HasCart $item
     * @param array $options
     * @param array $variation
     * @return $this
     */
    public function updateFromHasCart(HasCart $item, array $options = [], array $variation = []): CartItem
    {
        $this->id       = $item->getHasCartIdentifier();
        $this->name     = $item->getHasCartDescription();
        $this->price    = $item->getHasCartPrice();
        $this->priceTax = $this->price + $this->tax;
        $this->options = $options;
        $this->variation = $variation;

        return $this;
    }

    /**
     * Update the cart item from an array.
     *
     * @param array $attributes
     * @param array $options
     * @param array $variation
     * @return CartItem
     */
    public function updateFromArray(array $attributes, array $options = [], array $variation = []): CartItem
    {
        $this->id       = Arr::get($attributes, 'id', $this->id);
        $this->quantity = Arr::get($attributes, 'qty', $this->quantity);
        $this->name     = Arr::get($attributes, 'name', $this->name);
        $this->price    = Arr::get($attributes, 'price', $this->price);
        $this->priceTax = $this->price + $this->tax;
        $this->options  = Arr::get($attributes, 'options', $options);
        $this->variation  = Arr::get($attributes, 'variation', $variation);

        return $this;
    }

    /**
     * Get the formatted number.
     *
     * @param float  $value
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeparator
     * @return string
     */
    private function formatNumber($value, $decimals, $decimalPoint, $thousandSeparator): string
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
}
