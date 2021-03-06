<?php


namespace Tefo\Cart;


trait InteractsWithCard
{

    /**
     * Get the identifier of the HasCart item.
     *
     * @return int|string
     */
    public function getHasCartIdentifier()
    {
        return method_exists($this, 'getKey') ? $this->getKey() : $this->id;
    }

    /**
     * Get the description or title of the HasCart item.
     *
     * @return string
     */
    public function getHasCartDescription(): ?string
    {
        if(property_exists($this, 'name')) return $this->name;
        if(property_exists($this, 'title')) return $this->title;
        if(property_exists($this, 'description')) return $this->description;

        return null;
    }

    /**
     * Get the price of the HasCart item.
     *
     * @return float
     */
    public function getHasCartPrice(): ?float
    {
        return property_exists($this, 'price') ? $this->price : null;
    }
}
