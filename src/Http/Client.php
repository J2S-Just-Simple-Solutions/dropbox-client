<?php

namespace Dropboxv2\Http;

use Dropboxv2\App;
use Dropboxv2\Exceptions;
use Dropboxv2\Curl;

/**
 * HTTP Client class
 * @package Dropboxv2
 */
class Client
{

    const API_PATH_SEPARATOR = '/';

    const API_OAUTH2_ENDPOINT = "https://www.dropbox.com/";

    const API_FILE_ENDPOINT = "https://content.dropboxapi.com/2/";

    const API_CMD_ENDPOINT = "https://api.dropboxapi.com/2/";

    const API_NOTIFY_ENDPOINT = "https://notify.dropboxapi.com/2/";

    private $accessToken;

    private $clientIdentifier;

    /**
     * @var Dropboxv2\Curl\Curl $cURLHandler
     */
    private $cURLHandler;

    /**
     * @var array $httpHeaders
     */
    private $httpHeaders;

    /**
     * @var null|string $uploadContent
     */
    private $uploadContent;

    /**
     * @var bool $forceJson
     */
    private $forceJson;

    public function __construct($accessToken, $clientIdentifier, &$extraHeaders = [], $verbose = false)
    {
        $this->accessToken = $accessToken;
        $this->clientIdentifier = $clientIdentifier;
        $this->uploadContent = null;
        $this->forceJson = false;
        $this->initCurl($extraHeaders);
        $this->setCurlVerbose($verbose);
    }

    public function initCurl(&$extraHeaders = []) {
        $this->cURLHandler = new Curl\Curl();
        $this->httpHeaders = array_merge([
            'User-Agent: ' . $this->clientIdentifier . ' Dropboxv2-PHP-SDK/' . App::VERSION,
            'Authorization: Bearer '.$this->accessToken
        ], $extraHeaders);
        //$this->cURLHandler->setOpt(CURLOPT_HTTPHEADER, $this->httpHeaders);
        $this->cURLHandler->setOpt(CURLOPT_RETURNTRANSFER, 1);
    }

    public function setExtraHeader($key, $value, $separator = ':') {
        foreach($this->httpHeaders as $k => $httpHeader) {
            if (substr($httpHeader, 0, strlen($key)) === $key) {
                unset($this->httpHeaders[$k]);
                break;
            }
        }

        $this->httpHeaders [] = sprintf("%s %s %s", $key, $separator, $value);
    }

    public function setUploadContent($uploadContent = null) {
        $this->uploadContent = $uploadContent;
    }

    public function setCurlVerbose($verbose = true) {
        if ($verbose) {
            $this->cURLHandler->setOpt(CURLOPT_VERBOSE, 1);
        }
    }

    /**
     * Forcer le MimeType à JSON de la réponse
     *
     * @param bool $force
     */
    public function forceJSON($force = true) {
        $this->forceJson = $force;
    }

    /**
     * Lancement d'une requête de type GET
     *
     * @param string $url
     * @param string $type
     * @param array $params
     * @return mixed
     * @throws Exceptions\HttpRequestException
     * @throws Exceptions\JSONException
     */
    public function get($url, $type = 'cmd', $params = []) {
        $this->cURLHandler->setOpt(CURLOPT_URL, static::getUrl($type, $url));

        list($res, $statusCode) = $this->cURLHandler->exec($this->httpHeaders);
        static::checkHttpStatus($res, $statusCode);
        if ($res === false) {
            throw new Exceptions\HttpRequestException($this->cURLHandler->getLastError());
        }
        try {
            $response = new Response($this->getJSON($res));
            $res = $response->sanitize();
        } catch (Exceptions\JSONException $e) {
            throw $e;
        }

        $this->reset();

        return $res;
    }

    /**
     * Lancement d'une requête de type POST
     *
     * @param string $url
     * @param string $type
     * @param array $params
     * @return mixed
     * @throws Exceptions\HttpRequestException
     * @throws Exceptions\JSONException
     */
    public function post($url, $type = 'cmd', $params = []) {
        $this->cURLHandler->setOpt(CURLOPT_URL, static::getUrl($type, $url));
        $this->cURLHandler->setOpt(CURLOPT_POST, 1);

        if ($type === 'cmd') {
            $this->setHeaders('Content-Type', 'application/json');
            $this->cURLHandler->setOpt(CURLOPT_POSTFIELDS, json_encode($params));
        } else {
            if (!$this->isUpload()) {
                $this->setHeaders('Content-Type', '');
                //$this->setHeaders('Content-Length', 0);
            } else {
                $this->setHeaders('Content-Type', 'application/octet-stream');
                $this->cURLHandler->setOpt(CURLOPT_POSTFIELDS, $this->getUploadFile());
            }
            $this->setHeaders('Dropbox-API-Arg', json_encode($params, JSON_UNESCAPED_SLASHES));
        }

        list($res, $statusCode) = $this->cURLHandler->exec($this->httpHeaders);
        static::checkHttpStatus($res, $statusCode);
        if ($res === false) {
            throw new Exceptions\HttpRequestException($this->cURLHandler->getLastError());
        }
        if ($type === 'cmd' || $this->forceJson) {
            try {
                $response = new Response($this->getJSON($res));
                $res = $response->sanitize();
            } catch (Exceptions\JSONException $e) {
                throw $e;
            }
        }

        $this->reset();

        return $res;
    }

    /**
     * Lancement d'une requête de type POST (spécifique à la récupération de token)
     * @param string $url
     * @param string $type
     * @param array $params
     * @param string $clientId
     * @param string $authHeader
     * @return mixed
     * @throws Exceptions\HttpRequestException
     * @throws Exceptions\JSONException
     */
    public static function doPostWithAuth($url, $type, $params, $clientId, $authHeader) {
        $cURLHandler = new Curl\Curl();

        $cURLHandler->setOpt(CURLOPT_URL, static::getUrl($type, $url));
        $cURLHandler->setOpt(CURLOPT_POST, 1);
        $cURLHandler->setOpt(CURLOPT_RETURNTRANSFER, 1);

        $httpHeaders = [
            'User-Agent: ' . $clientId . " Dropboxv2-PHP-SDK/" . App::VERSION,
            'Authorization:' . $authHeader,
        ];
        $postFields = [];
        foreach($params as $k => $param) {
            $postFields [] = rawurlencode($k) . "=" . rawurlencode((string) $param);
        }
        $cURLHandler->setOpt(CURLOPT_POSTFIELDS, implode("&", $postFields));


        list($res, $statusCode) = $cURLHandler->exec($httpHeaders);
        static::checkHttpStatus($res, $statusCode);
        if ($res === false) {
            throw new Exceptions\HttpRequestException($cURLHandler->getLastError());
        }
        try {
            $response = new Response(static::getJSON($res));
            $res = $response->sanitize();
        } catch (Exceptions\JSONException $e) {
            throw $e;
        }

        return $res;
    }

    /**
     * UTILS
     */

    /**
     * @param $res
     * @param $httpStatus
     * @throws Exceptions\BadInputParamsException
     * @throws Exceptions\BadTokenException
     * @throws Exceptions\Exception
     * @throws Exceptions\InternalErrorException
     * @throws Exceptions\TooManyRequestsException
     */
    private static function checkHttpStatus($res, $httpStatus) {
        if ($httpStatus === 400) {
            throw new Exceptions\BadInputParamsException($res, $httpStatus);
        } else if ($httpStatus === 401) {
            throw new Exceptions\BadTokenException($res, $httpStatus);
        } else if ($httpStatus === 409) {
            throw new Exceptions\SpecificException($res, $httpStatus);
        } else if ($httpStatus === 429) {
            throw new Exceptions\TooManyRequestsException($res, $httpStatus);
        } else if ($httpStatus >= 500) {
            throw new Exceptions\InternalErrorException($res, $httpStatus);
        } else if ($httpStatus !== 200) {
            throw new Exceptions\Exception($res, $httpStatus);
        }
    }

    /**
     * @param $jsonStr
     * @return mixed
     * @throws Exceptions\JSONException
     */
    private static function getJSON($jsonStr) {
        $json = json_decode($jsonStr);
        $err = json_last_error();
        if ($err) {
            throw new Exceptions\JSONException($jsonStr, $err);
        } else {
            return $json;
        }
    }

    /**
     * Construction de l'URL complète de l'API
     *
     * @param string $type
     * @param string $url
     * @param array $params
     * @return string
     */
    public static function getUrl($type, $url, $params = []) {
        switch($type) {
            case 'file':
                    $prefix = static::API_FILE_ENDPOINT;
                break;
            case 'oauth2':
                    $prefix = static::API_OAUTH2_ENDPOINT;
                break;
            case 'notify':
                    $prefix = static::API_NOTIFY_ENDPOINT;
                break;
            case 'cmd':
            default:
                    $prefix = static::API_CMD_ENDPOINT;
                break;
        }

        return $prefix . $url . ((count($params) > 0) ? ('?' . http_build_query($params)) : '');
    }

    /**
     * Reset de cURL
     */
    private function reset() {
        $this->cURLHandler = null;
        $this->httpHeaders = [];
        $this->initCurl();
    }

    private function setHeaders($key, $value) {
        $httpHeader = sprintf("%s: %s", $key, $value);
        $this->setHeaderIfNotExist($httpHeader);
        //curl_setopt($this->cURLHandler, CURLOPT_HTTPHEADER, $this->httpHeaders);
    }

    private function setHeaderIfNotExist($httpHeader) {
        if (!in_array($httpHeader, $this->httpHeaders)) {
            $this->httpHeaders [] = $httpHeader;
        }
    }

    /**
     * Détermine si on veut uploader des données vers le serveur
     *
     * @return bool|int
     */
    private function isUpload() {
        $i=0;
        do {
            if (substr($this->httpHeaders[$i], 0, 11) === "data-binary") {
                return $i;
            }
            ++$i;
        } while ($i < count($this->httpHeaders));

        return ($this->uploadContent !== null);
    }

    /**
     * Récupère le contenu d'un fichier entier OU le contenu d'un bloc
     *
     * @return bool|null|string
     */
    private function getUploadFile() {
        $index = $this->isUpload();
        if ($index !== false) {
            $uploadFile = substr($this->httpHeaders[$index], 13);
            if ($this->uploadContent === null) {
                return file_get_contents($uploadFile);
            } else {
                return $this->uploadContent;
            }
        }
        return null;
    }

}