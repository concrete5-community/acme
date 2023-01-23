<?php

namespace Acme\Crypto;

use Acme\Exception\NotImplementedException;
use phpseclib\Crypt\Hash as Hash2;
use phpseclib3\Crypt\Hash as Hash3;

final class Hash
{
    /**
     * @var int
     */
    private $engineID;

    /**
     * @var \phpseclib\Crypt\Hash|\phpseclib3\Crypt\Hash
     */
    private $value;

    /**
     * @param string $hash
     * @param int|null $engineID The value of one of the Acme\Crypto\Engine constants
     */
    public function __construct($hash, $engineID = null)
    {
        $this->engineID = $engineID === null ? Engine::get() : $engineID;
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
                $this->value = new Hash2($hash);
                break;
            case Engine::PHPSECLIB3:
                $this->value = new Hash3($hash);
                break;
            default:
                throw new NotImplementedException();
        }
    }

    /**
     * @param string $text
     *
     * @return string
     */
    public function hash($text)
    {
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                return $this->value->hash($text);
            default:
                throw new NotImplementedException();
        }
    }
}
