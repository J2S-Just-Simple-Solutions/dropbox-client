<?php

namespace Dropboxv2;

use Dropboxv2\Exceptions;
use Dropboxv2\Enums;
use Dropboxv2\Http;

use \J2SPortal\utils\PortalFileSystem;

/**
 * Upload Session
 * @package Dropboxv2
 */
class UploadSession
{
    /**
     * Max file size for upload (150MB)
     */
    const UPLOAD_MAX_SIZE = 150000000;

    /**
     * Chunk size for upload (50MB)
     */
    const UPLOAD_CHUNK_SIZE = 50000000;

    /**
     * Dropbox file path
     * @var string $path
     */
    private $path;

    /**
     * Local file path
     * @var string $localPath
     */
    private $localPath;

    /**
     * Dropbox add mode
     * @var string $mode
     */
    private $mode;

    /**
     * @var bool $autoRename
     */
    private $autoRename;

    /**
     * @var bool $mute
     */
    private $mute;

    /**
     * @var Http\Client $httpClient
     */
    private $httpClient;

    /**
     * Upload session Id from Dropbox
     * @var null|string $sessionId
     */
    private $sessionId;

    public function __construct(&$httpClient, $path, $localPath, $mode = Enums\File::WRITE_MODE_ADD, $autoRename = false, $mute = false) {
        $this->path = $path;
        $this->localPath = $localPath;
        $this->mode = $mode;
        $this->autoRename = $autoRename;
        $this->mute = $mute;
        $this->sessionId = null;

        $this->httpClient = $httpClient;
    }

    public function reset() {
        $this->sessionId = null;
    }

    /**
     * Lancement de la session d'upload
     *
     * @return null
     */
    public function exec() {
        ini_set('memory_limit', -1);

        $result = null;
        $fileSize = filesize($this->localPath);

        $remaining = $fileSize;
        $offset = 0;

        do {
            $contentLength = $this->setUploadContent($offset);

            if ($offset === 0) {
                $this->start();
            } else {
                $this->append($offset);
            }

            $remaining -= $contentLength;
            $offset += $contentLength;

        }
        while ($remaining > static::UPLOAD_CHUNK_SIZE);

        $this->setUploadContent($offset);
        $result = $this->finish($offset);

        $this->reset();

        return $result;
    }

    private function start() {
        $this->httpClient->forceJSON();
        $res = $this->httpClient->post('files/upload_session/start', 'file', [
            "close" => false
        ]);
        $this->httpClient->forceJSON(false);

        $this->sessionId = $res->session_id;
    }

    private function append($offset) {
        $this->httpClient->post('files/upload_session/append_v2', 'file', [
            "cursor" => (object) [
                "session_id"    => $this->sessionId,
                "offset"        => $offset
            ],
            "close"             => false
        ]);
    }

    private function finish($offset) {
        $this->httpClient->forceJSON();
        $result = $this->httpClient->post('files/upload_session/finish', 'file', [
            "cursor"    => (object) [
                "session_id"    => $this->sessionId,
                "offset"        => $offset
            ],
            "commit"    => (object) [
                "path"          => $this->path,
                "mode"          => $this->mode,
                "autorename"    => $this->autoRename,
                "mute"          => $this->mute
            ]
        ]);
        $this->httpClient->forceJSON(false);

        return $result;
    }

    /**************
     *    UTILS
     **************/

    /**
     * @param int $offset
     * @return bool|string
     */
    private function readBytesFromFile($offset = 0) {
        $fh = fopen($this->localPath, 'rb');
        fseek($fh, $offset);
        $content = fread($fh, (static::UPLOAD_CHUNK_SIZE));
        fclose($fh);

        unset($fh);

        return $content;
    }

    private function setUploadContent($offset) {
        $content = $this->readBytesFromFile($offset);
        $contentLength = strlen($content);

        $this->httpClient->setExtraHeader("data-binary", $this->localPath, "@");
        $this->httpClient->setUploadContent($content);
        unset($content);

        return $contentLength;
    }

}