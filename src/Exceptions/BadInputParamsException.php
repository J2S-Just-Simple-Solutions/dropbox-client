<?php

namespace Dropboxv2\Exceptions;

use Throwable;

class BadInputParamsException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct("Bad input Params", $code, $previous, $message);
    }
}