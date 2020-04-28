<?php

namespace Dropboxv2;

use Dropboxv2\Security;
use Dropboxv2\Exceptions\CSRFTokenException;
use Dropboxv2\Exceptions\HttpRequestException;

class WebAuth extends Auth
{

    private $redirectUri;

    private $csrfTokenStore;

    public function __construct($appInfos, $clientId, $redirectUri, $csrfTokenStore) {
        parent::__construct($appInfos, $clientId);

        $this->redirectUri = $redirectUri;
        $this->csrfTokenStore = $csrfTokenStore;
    }

    public function start($urlState = null, $forceReapprove = false) {
        $csrfToken = static::encodeCsrfToken(Security::getRandomBytes(16));
        $state = $csrfToken;
        if ($urlState !== null) {
            $state .= "|";
            $state .= $urlState;
        }
        $this->csrfTokenStore->set($csrfToken);

        return $this->_getAuthorizeUrl($this->redirectUri, $state, $forceReapprove);
    }

    public function finish($queryParams) {

        list($code, $state, $urlState) = $this->checkDatas($queryParams);


        list($accessToken, $userId) = $this->_finish($code, $this->redirectUri);
        return [$accessToken, $userId, $urlState];
    }

    /**
     * UTILS
     */

    private static function encodeCsrfToken($string)
    {
        return strtr(base64_encode($string), '+/', '-_');
    }

    private function checkDatas($datas) {
        if (!isset($datas['state'])) {
            throw new HttpRequestException("Missing query parameter 'state'.");
        }

        $csrfTokenFromSession = $this->csrfTokenStore->get();
        $state = $datas['state'];

        $code = null;
        $error = null;
        $errorDescription = null;
        if (isset($datas['code'])) {
            $code = $datas['code'];
        }

        if ($code !== null && $error !== null) {
            throw new HttpRequestException("Query parameters 'code' and 'error' are both set;".
                " only one must be set.");
        }
        if ($code === null && $error === null) {
            throw new HttpRequestException("Neither query parameter 'code' or 'error' is set.");
        }

        // Check CSRF token

        if ($csrfTokenFromSession === null) {
            throw new CSRFTokenException("Invalid Token");
        }

        $splitPos = strpos($state, "|");
        if ($splitPos === false) {
            $givenCsrfToken = $state;
            $urlState = null;
        } else {
            $givenCsrfToken = substr($state, 0, $splitPos);
            $urlState = substr($state, $splitPos + 1);
        }
        if (!Security::stringEquals($csrfTokenFromSession, $givenCsrfToken)) {
            throw new CSRFTokenException("Token does not match");
        }
        $this->csrfTokenStore->clear();

        // Check for error identifier

        if ($error !== null) {
            if ($error === 'access_denied') {
                // When the user clicks "Deny".
                if ($errorDescription === null) {
                    throw new HttpRequestException("No additional description from Dropbox.");
                } else {
                    throw new HttpRequestException("Additional description from Dropbox: $errorDescription");
                }
            } else {
                // All other errors.
                $fullMessage = $error;
                if ($errorDescription !== null) {
                    $fullMessage .= ": ";
                    $fullMessage .= $errorDescription;
                }
                throw new HttpRequestException($fullMessage);
            }
        }

        return [$code, $state, $urlState];
    }

}