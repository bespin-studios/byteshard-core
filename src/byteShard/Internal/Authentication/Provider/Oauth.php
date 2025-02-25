<?php

namespace byteShard\Internal\Authentication\Provider;

use byteShard\DataModelInterface;
use byteShard\Internal\Authentication\AuthenticationInterface;
use byteShard\Internal\Authentication\OIDC;
use byteShard\Internal\Authentication\JWT;
use byteShard\Internal\Authentication\ProviderInterface;
use byteShard\Internal\Debug;
use byteShard\Internal\Login\Struct\Credentials;
use byteShard\Internal\Schema\DB\UserTable;
use Exception;
use League\OAuth2\Client\Provider\AbstractProvider;
use Stevenmaguire\OAuth2\Client\Provider\Keycloak;

class Oauth implements ProviderInterface
{
    private const ACCESS_TOKEN_COOKIE = 'access_token';
    private const REFRESH_TOKEN_COOKIE = 'refresh_token';
    private string $username;

    public function __construct(private readonly ?AbstractProvider $provider = null, private readonly string $certPath = '')
    {
    }

    public function userHasValidAndNotExpiredSession(int $sessionTimeoutInMinutes): bool
    {
        if (array_key_exists(self::ACCESS_TOKEN_COOKIE, $_COOKIE)) {
            $jwt          = new JWT($_COOKIE[self::ACCESS_TOKEN_COOKIE], $this->certPath);
            $tokenIsValid = $jwt->isTokenValid();
            if ($tokenIsValid === true) {
                $this->username = $jwt->getPreferredUsername();
                return true;
            }
        }
        return $this->refresh();
    }

    private function refresh(): bool
    {
        if (array_key_exists(self::REFRESH_TOKEN_COOKIE, $_COOKIE)) {
            $oidc = new OIDC($this->provider, true);
            $oidc->refresh($_COOKIE[self::REFRESH_TOKEN_COOKIE]);
            $newAccessToken = $oidc->getJwt();
            if (!empty($newAccessToken)) {
                $this->storeAccessToken($newAccessToken);
                return true;
            }
        }
        return false;
    }

    public function logout(): void
    {
        setcookie(self::ACCESS_TOKEN_COOKIE, '', time() - 3600, '/');
    }

    public function authenticate(?Credentials $credentials = null): bool
    {
        if ($this->provider === null) {
            throw new Exception('No Oauth Provider defined in Environment');
        }
        $oidc         = new OIDC($this->provider);
        $jwt          = new JWT($oidc->getJwt(), $this->certPath);
        $tokenIsValid = $jwt->isTokenValid();
        $accessToken  = $oidc->getJwt();
        $refreshToken = $oidc->getRefreshToken();
        if ($tokenIsValid) {
            $this->username = $jwt->getPreferredUsername();
            $test           = $jwt->getRealmAccessRoles();
            $this->storeAccessToken($accessToken);
            if (!empty($refreshToken)) {
                setcookie(self::REFRESH_TOKEN_COOKIE, $refreshToken, [
                    'expires'  => time() + 3600, // Token expiration time (you should sync this with the token's actual expiration)
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Strict',
                    'path'     => '/',
                ]);
            }
        }
        return $tokenIsValid;
    }

    public function getUsername(): string
    {
        return $this->username ?? '';
    }

    /**
     * @param string $accessToken
     * @return void
     */
    public function storeAccessToken(string $accessToken): void
    {
        $jwt = new JWT($accessToken, $this->certPath);
        setcookie(self::ACCESS_TOKEN_COOKIE, $accessToken, [
            'expires'  => time() + $jwt->tokenDuration(),
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
            'path'     => '/',
        ]);
    }

}
