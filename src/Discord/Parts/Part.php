<?php

/*
 * This file is a part of the DiscordPHP-Slash project.
 *
 * Copyright (c) 2021 David Cole <david.cole1340@gmail.com>
 *
 * This source file is subject to the MIT license which is
 * bundled with this source code in the LICENSE.md file.
 */

namespace Discord\Slash\Parts;

use ArrayAccess;
use JsonSerializable;

/**
 * Represents a part in the Discord servers.
 *
 * @author David Cole <david.cole1340@gmail.com>
 */
class Part implements ArrayAccess, JsonSerializable
{
    /**
     * Custom script data.
     * Used for storing custom information, used by end products.
     *
     * @var mixed
     */
    public $scriptData;

    /**
     * Array of fillable fields.
     *
     * @var array The array of attributes that can be mass-assigned.
     */
    protected $fillable = [];

    /**
     * Array of attributes.
     *
     * @var array The parts attributes and content.
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
     * Gets an attribute via key. Used for ArrayAccess.
     *
     * @param string $key The attribute key.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        return $this->__get($key);
    }

    /**
     * Checks if an attribute exists via key. Used for ArrayAccess.
     *
     * @param string $key The attribute key.
     *
     * @return bool Whether the offset exists.
     */
    public function offsetExists($key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Sets an attribute via key. Used for ArrayAccess.
     *
     * @param string $key   The attribute key.
     * @param mixed  $value The attribute value.
     */
    public function offsetSet($key, $value): void
    {
        $this->__set($key, $value);
    }

    /**
     * Unsets an attribute via key. Used for ArrayAccess.
     *
     * @param string $key The attribute key.
     */
    public function offsetUnset($key): void
    {
        if (isset($this->attributes[$key])) {
            unset($this->attributes[$key]);
        }
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
     * @param mixed  $value
     */
    public function __set(string $key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Returns the part in JSON serializable format.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }

    /**
     * Provides debugging information to PHP.
     *
     * @return array
     */
    public function __debugInfo()
    {
        $result = [];

        foreach ($this->fillable as $field) {
            $result[$field] = $this->{$field};
        }

        return $result;
    }
}
