<?php

namespace Dropboxv2\Http;

class Response
{
    private $_object;

    public function __construct($response) {
        $this->_object = $response;

        return $this;
    }


    public function sanitize() {
        $this->sanitize_rec($this->_object);

        return $this->_object;
    }

    private function sanitize_rec(&$parentObj) {
        foreach ($parentObj as $prop => $value) {
            if ($prop === ".tag") {
                $parentObj->tag = $parentObj->{".tag"};
                unset($parentObj->{".tag"});
            }
            if (is_array($value) || is_object($value)) {
                $_parent = (is_array($parentObj)) ? $parentObj[$prop] : $parentObj->{$prop};
                $this->sanitize_rec($_parent);
            }
        }
    }
}