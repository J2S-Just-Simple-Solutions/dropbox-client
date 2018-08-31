<?php

namespace Dropboxv2\Curl;

class Curl
{

    private $handler;

    public function __construct() {
        $this->handler = curl_init();

        $this->setOpt(CURLOPT_SSL_VERIFYHOST, 0);
        $this->setOpt(CURLOPT_SSL_VERIFYPEER, 0);
    }

    function __destruct()
    {
        curl_close($this->handler);
    }

    public function setOpt($opt, $value) {
        curl_setopt($this->handler, $opt, $value);
    }

    public function getLastError() {
        return curl_error($this->handler);
    }

    public function exec($headers) {
        $this->setOpt(CURLOPT_HTTPHEADER, $headers);

        $body = curl_exec($this->handler);
        $statusCode = curl_getinfo($this->handler, CURLINFO_HTTP_CODE);

        return [$body, $statusCode];
    }

}