<?php

namespace Dropboxv2;

use Dropboxv2\Exceptions\FileException;
use Dropboxv2\Exceptions\SpecificException;

use Dropboxv2\Enums;
use Dropboxv2\Http;

/**
 * Dropbox API v2 Client
 * @package Dropboxv2
 */
class Client
{
    const LONGPOLL_TIMEOUT = 30;

    /**
     * HTTP Client
     * @var Http\Client $_client
     */
    private $_client;

    /**
     * User Access Token
     * @var string $accessToken
     */
    private $accessToken;

    /**
     * Application identifier
     * @var string $clientIdentifier
     */
    private $clientIdentifier;

    /**
     * Options
     *
     * @var object
     */
    private $options;

    /**
     * DropboxClient constructor.
     * @param string $accessToken
     * @param string $clientIdentifier
     * @param mixed  $options
     */
    public function __construct($accessToken, $clientIdentifier, $options = null) {
        $this->accessToken = $accessToken;
        $this->clientIdentifier = $clientIdentifier;
        $this->_client = new Http\Client($accessToken, $clientIdentifier);
        $this->options = $this->_getOptions($options);
    }

    /*****************************
     *          FOLDER
     *****************************/

    /**
     * Créé un nouveau dossier
     *
     * @param string $path
     * @param bool $autoRename
     * @return mixed
     */
    public function createFolder($path, $autoRename = false) {
        if ($this->folderExists($path)) return false;
        
        return $this->_request('files/create_folder_v2', [
            'path'          => $path,
            'autorename'    => $autoRename
        ]);
    }

    /**
     * Vérification de l'existence d'un dossier sur Dropbox (listing)
     *
     * @param string $path
     * @return bool
     */
    public function folderExists($path) {
        return $this->fileExists($path);
    }

    /**
     * Liste le contenu d'un dossier
     *
     * @param string $folderPath Chemin (Dropbox) du dossier
     * @param bool $recursive On récupère le contenu des dossiers enfants?
     * @param string|null $cursor On récupère la suite des éléments [optional]
     * @return mixed
     */
    public function listFolder($folderPath, $recursive = false, $cursor = null) {
        $url = 'files/list_folder';
        if ($cursor) {
            $url .= '/continue';
            $params = [
                'cursor' => $cursor,
            ];
        } else {
            $params = [
                'path' => $folderPath,
                'recursive' => $recursive
            ];
        }
        return $this->_request($url, $params);
    }

    public function listFolderLongPoll($cursor, $timeout = Client::LONGPOLL_TIMEOUT) {
        return $this->_request('files/list_folder/longpoll', [
            "cursor"    => $cursor,
            "timeout"   => $timeout
        ], 'notify');
    }

    /*****************************
     *          FILE
     *****************************/

    /*----------------------------
     *          IMAGES
     *----------------------------*/

    /**
     * Récupère la preview d'un document.
     * Formats supportés : .doc, .docx, .docm, .ppt, .pps, .ppsx, .ppsm, .pptx, .pptm, .xls, .xlsx, .xlsm, .rtf
     *
     * @param string $path
     * @return mixed
     */
    public function getPreview($path) {
        return $this->_request('files/get_preview', [
            'path'  => $path
        ], 'file');
    }

    /**
     * Récupère la thumbnail d'une image
     * Formats supportés :  jpg, jpeg, png, tiff, tif, gif and bmp
     * Taille max : 20MB
     *
     * @param string $path
     * @param string $format
     * @param string $size
     * @return mixed
     */
    public function getThumbnail($path, $format = Enums\Image::IMG_FORMAT_JPEG, $size = Enums\Image::IMG_SIZE_64X64) {
        return $this->_request('files/get_thumbnail', [
            'path'      => $path,
            'format'    => $format,
            'size'      => $size
        ], 'file');
    }

    /*----------------------------
     *          MISCS
     *----------------------------*/

    /**
     * Suppression de fichier/dossier
     *
     * @param string $path
     * @return mixed
     */
    public function remove($path) {
        return $this->_request('files/delete_v2', [
            'path'  => $path
        ]);
    }

    /**
     * Récupération des infos d'un fichier/dossier
     *
     * @param string $path
     * @return mixed
     */
    public function getMetaData($path) {
        return $this->_request('files/get_metadata', ['path' => $path]);
    }

    /**
     * Recherche d'un fichier dans un dossier
     *
     * @param string $path
     * @param string $query
     * @param int $start
     * @param int $limit
     * @param string $mode
     * @return mixed
     */
    public function search($path, $query, $start = 0, $limit = 100, $mode = Enums\File::SEARCH_MODE_FILENAME) {
        return $this->_request('files/search', [
            "path"          => $path,
            "query"         => $query,
            "start"         => $start,
            "max_results"   => $limit,
            "mode"          => $mode
        ]);
    }

    /**
     * Vérification de l'existence d'un fichier sur Dropbox (recherche)
     *
     * @param string $path
     * @return bool
     */
    public function fileExists($path) {
        $changed = $this->skipLogError(); // if needed

        try {
            $searchResults = $this->getMetaData($path);
        } catch (SpecificException $e) { // Path not found exception
            return false;
        }

        if ($changed) {
            $this->skipLogError(false);
        }

        return true;
    }

    /**
     * Demande une "copy_reference"
     * pour pouvoir copier un fichier ou un dossier sur un autre Dropbox
     * @see Client::pasteReference()
     *
     * @param string $path
     * @return mixed
     */
    public function copyReference($path) {
        return $this->_request('files/copy_reference/get', [
            'path'  => $path
        ]);
    }

    /**
     * Demande de sauvegarde d'une "copy_reference"
     * obtenue avec copyReference()
     * @see Client::copyReference()
     *
     * @param string $path
     * @param string $reference
     * @return mixed
     */
    public function pasteReference($path, $reference) {
        return $this->_request('files/copy_reference/save', [
            'copy_reference'    => $reference,
            'path'              => $path
        ]);
    }

    /*----------------------------
     *          DOWNLOAD
     *----------------------------*/

    /**
     * Téléchargement d'un fichier via un URL
     *
     * @param string $path
     * @param string $url
     */
    public function downloadURL($path, $url) {
        $res = $this->_downloadURL($path, $url);

        if (isset($res->async_job_id)) {
            do {
                $isComplete = $this->_checkDownloadURLJob($res->async_job_id);
            } while (!$isComplete);
        }
    }

    /**
     * Lancement du job de téléchargement d'URL
     *
     * @param string $path
     * @param string $url
     * @return mixed
     */
    private function _downloadURL($path, $url) {
        return $this->_request('files/save_url', [
            'path'  => $path,
            'url'   => $url
        ]);
    }

    /**
     * Vérification de l'état d'un job de téléchargement d'URL
     *
     * @param string $jobId
     * @return bool
     */
    private function _checkDownloadURLJob($jobId) {
        $res = $this->_request('files/save_url/check_job_status', [
            'async_job_id'  => $jobId
        ]);

        return (isset($res->tag) && $res->tag === "complete");
    }

    /**
     * Télécharge un fichier depuis Dropbox
     *
     * Si un path est renseigné, on le sauvegarde dans un fichier.
     * Sinon, on retourne directement le contenu.
     *
     * @param string $path Chemin (Dropbox) du fichier
     * @param string $localPath Chemin (Local) du fichier [optional]
     * @return int|mixed
     * @throws FileException
     */
    public function download($path, $localPath) {
        if (is_dir($localPath)) {
            throw new FileException($localPath . " is a directory");
        }

        $dirpath = pathinfo($localPath, PATHINFO_DIRNAME);
        if (!file_exists($dirpath)) {
            mkdir($dirpath);
        }

        $fileData = $this->_request('files/download', ['path' => $path], 'file');

        if ($localPath !== "") {
            $res = file_put_contents($localPath, $fileData);
            if ($res === false) {
                throw new FileException($localPath);
            }
            return $res;
        }

        return $fileData;
    }

	/**
     * Télécharge un fichier depuis Dropbox au format ZIP
     *
     * Si un path est renseigné, on le sauvegarde dans un fichier.
     * Sinon, on retourne directement le contenu.
     *
     * @param string $path Chemin (Dropbox) du fichier
     * @param string $localPath Chemin (Local) du fichier [optional]
     * @return int|mixed
     * @throws FileException
     */
	public function downloadAsZIP($path, $localPath) {
		if (is_dir($localPath)) {
            throw new FileException($localPath . " is a directory");
        }

        $dirpath = pathinfo($localPath, PATHINFO_DIRNAME);
        if (!file_exists($dirpath)) {
            mkdir($dirpath);
        }

		$fileData = $this->_request('files/download_zip', ['path' => $path], 'file');

		if ($localPath !== "") {
            $res = file_put_contents($localPath, $fileData);
            if ($res === false) {
                throw new FileException($localPath);
            }
            return $res;
        }

        return $fileData;
	}

    /*----------------------------
     *          UPLOAD
     *----------------------------*/

    /**
     * Upload un fichier local sur Dropbox
     *
     * @param string $path Chemin (Dropbox) du fichier
     * @param string $localPath Chemin (Local) du fichier
     * @param string $mode Mode d'écriture
     * @param bool $autoRename
     * @param bool $mute
     * @param mixed $updateRev
     * @return mixed
     * @throws FileException
     */
    public function upload($path, $localPath, $mode = Enums\File::WRITE_MODE_ADD, $autoRename =false, $mute = false, $updateRev = null) {
        if (filesize($localPath) > UploadSession::UPLOAD_MAX_SIZE) {
            return $this->upload_session($path, $localPath);
        }

        $dataObj = (object) [
            "type"  => "file",
            "data"  => $localPath
        ];

        return $this->_upload($path, $dataObj, $mode, $autoRename, $mute, $updateRev);
    }

    /**
     * Upload des données brutes sur Dropbox
     *
     * @param string $path
     * @param string $datas
     * @param string $mode
     * @param bool $autoRename
     * @param bool $mute
     * @param mixed $updateRev
     * @return mixed
     * @throws FileException
     */
    public function uploadFromBinary($path, $datas, $mode = Enums\File::WRITE_MODE_ADD, $autoRename =false, $mute = false, $updateRev = null) {
        if (strlen($datas > UploadSession::UPLOAD_MAX_SIZE)) {
            throw new FileException("");
        }

        $dataObj = (object) [
            "type"  => "raw",
            "data"  => $datas
        ];

        return $this->_upload($path, $dataObj, $mode, $autoRename, $mute, $updateRev);
    }

    /**
     * Fonction d'upload
     *
     * @param string $path
     * @param \stdClass $dataObject
     * @param string $mode
     * @param bool $autoRename
     * @param bool $mute
     * @param mixed $updateRev
     * @return mixed
     */
    private function _upload($path, $dataObject, $mode = Enums\File::WRITE_MODE_ADD, $autoRename = false, $mute = false, $updateRev = null) {
        if ($mode === Enums\File::WRITE_MODE_UPDATE) {
            $mode = (object) [
                '.tag'      => 'update',
                'update'    => $updateRev
            ];
        }

        $extraHeaders = [];
        $uploadContent = null;
        if ($dataObject->type === "file") { $extraHeaders = [ sprintf('data-binary "@%s"', $dataObject->data) ]; }
        else { $uploadContent = $dataObject->data; }

        return $this->_request('files/upload', [
            'path'          => $path,
            'mode'          => $mode,
            'autorename'    => $autoRename,
            'mute'          => $mute
        ], 'file', 'POST', $extraHeaders, $uploadContent);
    }

    /**
     * Démarre une nouvelle session d'upload
     *
     * @param string $path
     * @param string $localPath
     * @param string $mode
     * @param bool $autoRename
     * @param bool $mute
     * @return mixed
     */
    public function upload_session($path, $localPath, $mode = Enums\File::WRITE_MODE_ADD, $autoRename = false, $mute = false) {
        $uploadSession = new UploadSession($this->_client, $path, $localPath, $mode, $autoRename, $mute);
        return $uploadSession->exec();
    }

    /*****************************
     *          USER
     *****************************/

    /**
     * L'utilisateur courant
     *
     * @return mixed
     */
    public function getCurrentUser() {
        return $this->_request('users/get_current_account', null);
    }

    /*****************************
     *          UTILS
     *****************************/

    /**
     * Lance une requête HTTP vers l'API Dropbox
     *
     * @internal
     * @param string $url          URL d'appel de l'API
     * @param array $params        Paramètres pour l'appel
     * @param string $type         TYPE de requête
     * @param string $method       Méthode d'envoi des données (POST / GET)
     * @param array $extraHeaders  Headers supplémentaires
     * @param mixed $uploadContent Contenu de l'upload
     * @return mixed
     * @throws \Exception
     */
    private function _request($url, $params = [], $type = 'cmd', $method = 'POST', $extraHeaders = [], $uploadContent = null) {
        $httpResult = null;
        try {
            if (!empty($extraHeaders)) {
                $httpClient = new Http\Client($this->accessToken, $this->clientIdentifier, $extraHeaders);
            } else {
                $httpClient = $this->_client;
            }
            if ($uploadContent !== null) {
                $httpClient->setUploadContent($uploadContent);
            }
            $httpResult = $httpClient->{strtolower($method)}($url, $type, $params);
        } catch(\Exception $e) {
            if (!$this->isSkippingLogError() || $this->getLogErrorCount() > 0) {
                error_log("[DROPBOXV2] ERROR (request on $url) : " . print_r($e, true));
            }

            throw $e;
        }

        if ($this->isSkippingLogError() && $this->getLogErrorCount() > 0) {
            $this->subLogErrorCount();
        }

        return $httpResult;
    }

    /*----------------------------
     *          LOG
     *----------------------------*/

    /**
     * Arrêter le log des erreurs HTTP
     *
     * @param boolean $skip
     * @return boolean 
     */
    private function skipLogError($skip = true) {
        if (!$this->isSkippingLogError()) {
            $this->options->log->error->skip = $skip;
            return true;
        }
        return false;
    }

    /**
     * Est-ce que le log des erreurs HTTP est arrêté ?
     *
     * @return boolean
     */
    private function isSkippingLogError() {
        return $this->options->log->error->skip;
    }

    /**
     * Récupère le nombre de requêtes HTTP à ne pas tracer
     *
     * @return integer
     */
    private function getLogErrorCount() {
        return (isset($this->options->log->error->count) ? $this->options->log->error->count : 0);
    }

    /**
     * Initialise le nombre de requêtes à ne pas tracer
     *
     * @param integer  $count
     * @return void
     */
    private function setLogErrorCount($count) {
        $this->options->log->error->count = $count;
    }

    /**
     * Retire n au compteurs
     *
     * @param integer $sub
     * @return void
     */
    private function subLogErrorCount($sub = 1) {
        $this->options->log->error->count -= $sub;
    }

    /*----------------------------
     *          OPTIONS
     *----------------------------*/

    /**
      * Récupère les options par défaut et les merge avec celles passées en paramètre
      *
      * @param mixed $options
      * @return object
      */
    private function _getOptions($options = null) {
        $_options = $this->_getDefaultOptions();

        return (object) ($options ? array_merge((array) $_options, (array) $options) : $_options);
    }

    /**
     * Retourne les options par défaut
     *
     * @return object
     */
    private function _getDefaultOptions() {
        return (object) [
            'log'   => (object) [
                'error' => (object) [
                    'skip' => false
                ]
            ]
        ];
    }
}