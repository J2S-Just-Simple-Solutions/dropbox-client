<?php

namespace Dropboxv2\Exceptions;

use Throwable;

class SpecificException extends Exception
{

    private $_message;

    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        $this->_message = $message;
        $message = $this->parseMessage($message);
        parent::__construct($message, $code, $previous);
    }

    private function parseMessage($message) {
        $json = json_decode($message);
        $err = json_last_error();

        if ($err) {
            return $message;
        } else {

            $parts = explode("/", $json->error_summary);

            foreach($parts as $k => $part) {
                if ($part === "..." || $part === ".." || $part === ".") { unset($parts[$k]); }
            }

            return implode(" - ", $parts);
        }
    }

}