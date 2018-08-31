<?php

namespace Dropboxv2;

class App
{

    const VERSION = '0.1.0';

    private $key;

    private $secret;

    private $accessToken;

    public function __construct($key, $secret, $accessToken = null)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->accessToken = $accessToken;
    }

    public function getKey() {
        return $this->key;
    }

    public function getSecret() {
        return $this->secret;
    }

    public function getAccessToken() {
        return $this->accessToken;
    }

    public function setAccessToken($accessToken) {
        $this->accessToken = $accessToken;
    }


    public static function loadFromJSONFile($jsonFile) {
        if (file_exists($jsonFile)) {
            $json = json_decode(file_get_contents($jsonFile));
            return new App($json->key, $json->secret);
        }
        return null;
    }

    public static function loadFromJSON($json) {
        if ($json) {
            return new App($json->key, $json->secret);
        }
        return null;
    }
}