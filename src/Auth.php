<?php

namespace Dropboxv2;

use Dropboxv2\Exceptions\Exception;

use Dropboxv2\Http;

class Auth
{

    /**
     * @var App
     */
    private $appInfos;

    private $clientId;

    public function __construct($appInfos, $clientId) {
        $this->appInfos = $appInfos;
        $this->clientId = $clientId;
    }

    protected function _getAuthorizeUrl($redirectUri, $state, $forceReapprove = false)
    {
        if ($forceReapprove === false) {
            $forceReapprove = null;  // Don't include it in the URL if it's the default value.
        }

        return Http\Client::getUrl('oauth2', "oauth2/authorize", [
            "response_type"     => "code",
            "client_id"         => $this->appInfos->getKey(),
            "redirect_uri"      => $redirectUri,
            "state"             => $state,
            "force_reapprove"   => $forceReapprove,
        ]);
    }

    protected function _finish($code, $originalRedirectUri)
    {
        // This endpoint requires "Basic" auth.
        $clientCredentials = $this->appInfos->getKey() . ":" . $this->appInfos->getSecret();
        $authHeaderValue = "Basic " . base64_encode($clientCredentials);

        $response = Http\Client::doPostWithAuth("oauth2/token", 'oauth2', [
            "grant_type"    => "authorization_code",
            "code"          => $code,
            "redirect_uri"  => $originalRedirectUri,
        ], $this->clientId, $authHeaderValue);

        if (!property_exists($response, 'token_type') || !is_string($response->token_type)) {
            throw new Exception("Missing \"token_type\" field.");
        }

        if (!property_exists($response, 'access_token') || !is_string($response->access_token)) {
            throw new Exception("Missing \"access_token\" field.");
        }

        if (!property_exists($response, 'uid') || !is_string($response->uid)) {
            throw new Exception("Missing \"uid\" string field.");
        }
        $tokenType = $response->token_type;
        $accessToken = $response->access_token;
        $userId = $response->uid;

        if (strtolower($tokenType) !== "bearer") {
            throw new Exception("Unknown \"token_type\"; expecting \"Bearer\"");
        }

        return [$accessToken, $userId];
    }


}