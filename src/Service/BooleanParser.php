<?php

namespace Acme\Service;

defined('C5_EXECUTE') or die('Access Denied.');

class BooleanParser
{
    /**
     * Convert a value to a boolean.
     *
     * @param mixed $value
     * @param bool $allowNull
     *
     * @return bool|null NULL if $allowNull is true
     */
    public function toBoolean($value, $allowNull = false)
    {
        switch (gettype($value)) {
            case 'boolean':
                return $value;
            case 'integer':
                return $value !== 0;
            case 'double':
                return $value !== 0.0;
            case 'string':
                $value = strtolower(trim($value));
                switch (strtolower($value)) {
                    case '':
                        return $allowNull ? null : false;
                    case 'yes':
                    case 'y':
                    case 'true':
                    case 't':
                        return true;
                    case 'no':
                    case 'n':
                    case 'false':
                    case 'f':
                        return false;
                }
                if ($value === (string) (int) $value) {
                    return $value !== '0';
                }
                break;
            case 'NULL':
                return $allowNull ? null : false;
        }

        return (bool) $value;
    }
}
