<?php

namespace Discord\Slash\Parts;

class Part
{
    /**
     * Array of attributes.
     *
     * @var array
     */
    protected $attributes;

    /**
     * Part constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        $this->attributes = (array) $attributes;
    }

    /**
     * Returns the parts attributes.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Gets an attribute from the part.
     *
     * @param string $key
     */
    public function __get(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Sets an attribute in the part.
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set(string $key, $value)
    {
        $this->attributes[$key] = $value;
    }
}
