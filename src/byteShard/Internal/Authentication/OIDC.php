<?php

namespace byteShard\Internal\Authentication;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;

class OIDC
{
    private ?AccessTokenInterface $token = null;

    public function __construct(
        private readonly AbstractProvider $provider,
        bool                              $emptyConstructor = false
    ) {
        if ($emptyConstructor) {
            return;
        }
        if (!isset($_GET['code'])) {
            $this->redirectToAuthProvider();
        }
        if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
            unset($_SESSION['code_verifier']);
            exit('Invalid state, make sure HTTP sessions are enabled.');
        }
        unset($_SESSION['oauth2state']);
        $this->token = $this->getAccessToken($_GET['code']);
    }


    public function getJwt(): string
    {
        return $this->token?->getToken() ?? '';
    }

    public function getRefreshToken(): string
    {
        return $this->token?->getRefreshToken() ?? '';
    }

    public function getRefreshExpiry(): ?int
    {
        $values = $this->token?->getValues();
        if (!isset($values) && array_key_exists('refresh_expires_in', $values)) {
            return $values['refresh_expires_in'];
        }
        return null;
    }

    private function redirectToAuthProvider(): never
    {
        $codeVerifier              = bin2hex(random_bytes(64));
        $codeChallenge             = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $authUrl                   = $this->provider->getAuthorizationUrl(
            [
                'code_challenge'        => $codeChallenge,
                'code_challenge_method' => 'S256',
            ]
        );
        $_SESSION['oauth2state']   = $this->provider->getState();
        $_SESSION['code_verifier'] = $codeVerifier;
        header('Location: '.$authUrl);
        exit;
    }

    /**
     * @throws IdentityProviderException
     * @throws GuzzleException
     */
    public function refresh(string $refreshToken): void
    {
        $this->token = $this->provider->getAccessToken('refresh_token', ['refresh_token' => $refreshToken]);
    }

    private function getAccessToken(string $code): AccessTokenInterface
    {
        $codeVerifier = $_SESSION['code_verifier'];
        unset($_SESSION['code_verifier']);
        try {
            return $this->provider->getAccessToken(
                'authorization_code',
                [
                    'code'          => $code,
                    'code_verifier' => $codeVerifier,
                ]
            );
        } catch (Exception|GuzzleException $e) {
            exit('Failed to get access token: '.$e->getMessage());
        }
    }

    public function getResourceOwner(): ResourceOwnerInterface
    {
        try {
            return $this->provider->getResourceOwner($this->token);
        } catch (Exception|GuzzleException $e) {
            exit('Failed to get resource owner: '.$e->getMessage());
        }
    }
}
