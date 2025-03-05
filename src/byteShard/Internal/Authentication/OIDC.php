<?php

namespace byteShard\Internal\Authentication;

use byteShard\Internal\Server;
use Exception;
use League\OAuth2\Client\Provider\AbstractProvider;
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

    private function redirectToAuthProvider(): never
    {
        $authUrl                 = $this->provider->getAuthorizationUrl();
        $_SESSION['oauth2state'] = $this->provider->getState();
        header('Location: '.$authUrl);
        exit;
    }

    public function refresh(string $refreshToken): void
    {
        try {
            $this->token = $this->provider->getAccessToken('refresh_token', ['refresh_token' => $refreshToken]);
        } catch (Exception) {
            header('Location: '.Server::getBaseUrl().'/login/');
            exit;
        }
    }

    private function getAccessToken(string $code): AccessTokenInterface
    {
        try {
            return $this->provider->getAccessToken('authorization_code', [
                'code' => $code
            ]);
        } catch (Exception $e) {
            exit('Failed to get access token: '.$e->getMessage());
        }
    }

    public function getResourceOwner(): ResourceOwnerInterface
    {
        try {
            return $this->provider->getResourceOwner($this->token);
        } catch (Exception $e) {
            exit('Failed to get resource owner: '.$e->getMessage());
        }
    }
}
