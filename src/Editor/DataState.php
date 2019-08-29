<?php

namespace Acme\Editor;

use ArrayAccess;

defined('C5_EXECUTE') or die('Access Denied.');

class DataState
{
    /**
     * @var array
     */
    protected $input;

    /**
     * @var \ArrayAccess
     */
    protected $errors;

    /**
     * @var bool
     */
    protected $failed = false;

    /**
     * @param array $input
     * @param \ArrayAccess $errors
     */
    public function __construct(array $input, ArrayAccess $errors)
    {
        $this->input = $input;
        $this->errors = $errors;
    }

    /**
     * @return \ArrayAccess
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Check if there's a value with a specific key (even if it's null).
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasValue($key)
    {
        return array_key_exists($key, $this->input);
    }

    /**
     * Pop a value out of the input array.
     *
     * @param string $key
     * @param mixed $default What to return when $key does not exist in the data
     *
     * @return mixed
     */
    public function popValue($key, $default = null)
    {
        if ($this->hasValue($key) === false) {
            return null;
        }
        $value = $this->input[$key];
        unset($this->input[$key]);

        return $value;
    }

    /**
     * Add an error to the error list.
     *
     * @param string $description
     *
     * @return $this
     */
    public function addError($description)
    {
        $this->failed = true;
        $this->errors[] = $description;

        return $this;
    }

    /**
     * Some errors were added to this instance?
     *
     * @return bool
     */
    public function isFailed()
    {
        return $this->failed;
    }

    /**
     * Mark the state as failed.
     *
     * @return $this
     */
    public function setFailed()
    {
        $this->failed = true;

        return $this;
    }

    /**
     * Get the keys still existing in the input array.
     *
     * @return string
     */
    public function getRemainingKeys()
    {
        return array_keys($this->input);
    }
}
