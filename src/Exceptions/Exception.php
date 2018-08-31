<?php

namespace Dropboxv2\Exceptions;

use Throwable;

class Exception extends \Exception
{
    protected $oContent;

    public function __construct($message = "", $code = 0, Throwable $previous = null, $oContent = null)
    {
        parent::__construct($message, $code, $previous);

        $this->oContent = $oContent;
    }

    protected function setOriginalContent($content) {
        $this->oContent = $content;
    }

    protected function getOriginalContent() {
        return $this->oContent;
    }

}