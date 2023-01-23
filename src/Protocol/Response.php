<?php

namespace Acme\Protocol;

use DateTime;

defined('C5_EXECUTE') or die('Access Denied.');

final class Response
{
    /**
     * Response type: other/unknown.
     *
     * @var int
     */
    const TYPE_OTHER = 0;

    /**
     * Response type: JSON.
     *
     * @var int
     */
    const TYPE_JSON = 1;

    /**
     * Response type: JSON with errors.
     *
     * @var int
     */
    const TYPE_JSONERROR = 2;

    /**
     * The HTTP code of the response.
     *
     * @var int
     */
    private $code;

    /**
     * The response type (one of the TYPE_... constants).
     *
     * @var int
     */
    private $type;

    /**
     * The contents of the response.
     *
     * @var mixed
     */
    private $data;

    /**
     * The "location" contained in the response.
     *
     * @var string
     */
    private $location = '';

    /**
     * The "links" contained in the response.
     *
     * @var array
     */
    private $links = [];

    /**
     * The value of the "Retry-After" header (if present).
     *
     * @var \DateTime|null
     */
    private $retryAfter;

    private function __construct()
    {
    }

    /**
     * Create a new instance.
     *
     * @param int $code the HTTP code of the response
     * @param int $type the response type (one of the TYPE_... constants)
     *
     * @return static
     */
    public static function create($code, $type)
    {
        $result = new static();
        $result->code = (int) $code;
        $result->type = (int) $type;

        return $result;
    }

    /**
     * Get the HTTP code of the response.
     *
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Get the response type (one of the TYPE_... constants).
     *
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get the contents of the response.
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the error identifier contained in the message (if available).
     *
     * @return string
     */
    public function getErrorIdentifier()
    {
        return is_array($this->data) ? (string) array_get($this->data, 'type', '') : '';
    }

    /**
     * Get the error description contained in the message (if available).
     *
     * @return string
     */
    public function getErrorDescription()
    {
        return is_array($this->data) ? (string) array_get($this->data, 'detail', '') : '';
    }

    /**
     * Set the contents of the response.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function setData($value)
    {
        $this->data = $value;

        return $this;
    }

    /**
     * Get the "location" contained in the response.
     *
     * @return string
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Set the "location" contained in the response.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setLocation($value)
    {
        $this->location = (string) $value;

        return $this;
    }

    /**
     * Get the "links" contained in the response.
     *
     * @return array
     */
    public function getLinks()
    {
        return $this->links;
    }

    /**
     * Get a specific link contained in the response.
     *
     * @param string $rel
     *
     * @return string Empty string if $rel is not valid
     */
    public function getLink($rel)
    {
        return isset($this->links[$rel]) ? $this->links[$rel] : '';
    }

    /**
     * Add a "link" contained in the response.
     *
     * @param string $rel
     * @param string $value
     *
     * @return $this
     */
    public function addLinks($rel, $value)
    {
        $this->links[$rel] = (string) $value;

        return $this;
    }

    /**
     * Get the value of the "Retry-After" header (if present).
     *
     * @return string
     */
    public function getRetryAfter()
    {
        return $this->retryAfter;
    }

    /**
     * Set the value of the "Retry-After" header (if present).
     *
     * @return $this
     */
    public function setRetryAfter(DateTime $value = null)
    {
        $this->retryAfter = $value;

        return $this;
    }
}
