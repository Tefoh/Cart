<?php

namespace Tefo\Tests\Cart\Fixtures;

use Tefo\Cart\HasCart;

class Product implements HasCart
{
    /**
     * @var int|string
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var float
     */
    private $price;

    /**
     * HasCartProduct constructor.
     *
     * @param int|string $id
     * @param string     $name
     * @param float      $price
     */
    public function __construct($id = 1, $name = 'Item name', $price = 10.00)
    {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
    }

    /**
     * Get the identifier of the HasCart item.
     *
     * @param null $options
     * @return int|string
     */
    public function getHasCartIdentifier($options = null)
    {
        return $this->id;
    }

    /**
     * Get the description or title of the HasCart item.
     *
     * @param null $options
     * @return string
     */
    public function getHasCartDescription($options = null): string
    {
        return $this->name;
    }

    /**
     * Get the price of the HasCart item.
     *
     * @param null $options
     * @return float
     */
    public function getHasCartPrice($options = null): float
    {
        return $this->price;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
        ];
    }
}
