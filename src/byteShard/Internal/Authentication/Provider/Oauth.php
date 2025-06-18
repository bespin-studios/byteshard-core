<?php

namespace byteShard\Internal\Authentication\Provider;

use byteShard\Authentication\JWTProperties;
use byteShard\Authentication\User;
use byteShard\Internal\Authentication\JWT;
use byteShard\Internal\Authentication\OIDC;
use byteShard\Internal\Authentication\ProviderInterface;
use byteShard\Internal\Authentication\Providers;
use byteShard\Internal\Config;
use byteShard\Internal\Login\Struct\Credentials;
use byteShard\Internal\Server;
use Exception;
use League\OAuth2\Client\Provider\AbstractProvider;

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
        try {
            if (array_key_exists(self::REFRESH_TOKEN_COOKIE, $_COOKIE)) {
                $oidc = new OIDC($this->provider, true);
                $oidc->refresh($_COOKIE[self::REFRESH_TOKEN_COOKIE]);
                $newAccessToken = $oidc->getJwt();
                if (!empty($newAccessToken)) {
                    $this->storeToken($newAccessToken);
                    $refreshToken = $oidc->getRefreshToken();
                    if (!empty($refreshToken)) {
                        $this->storeToken($refreshToken, self::REFRESH_TOKEN_COOKIE, $oidc->getRefreshExpiry());
                    }
                    return true;
                }
            }
        } catch (Exception) {
            $this->logout();
            header('Location: '.Server::getBaseUrl().'/login/');
            exit;
        }
        return false;
    }

    public function logout(): void
    {
        setcookie(self::ACCESS_TOKEN_COOKIE, '', time() - 3600, '/');
        setcookie(self::REFRESH_TOKEN_COOKIE, '', time() - 3600, '/');
        User::logout();
    }

    /**
     * @throws \byteShard\Exception
     */
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
            $parsedJwt      = $jwt->getJwt();
            $claims         = Config::getConfig()->getConfiguredClaims();
            $user           = User::createUser(
                username: $this->username,
                firstname: $parsedJwt->{$claims[JWTProperties::Firstname->value]} ?? '',
                lastname: $parsedJwt->{$claims[JWTProperties::Lastname->value]} ?? '',
                mail: $parsedJwt->{$claims[JWTProperties::Email->value]} ?? '',
                provider: Providers::OAUTH
            );
            if (isset($parsedJwt->{$claims[JWTProperties::Groups->value]})) {
                if (is_array($parsedJwt->{$claims[JWTProperties::Groups->value]})) {
                    foreach ($parsedJwt->{$claims[JWTProperties::Groups->value]} as $group) {
                        $user->addGroup($group);
                    }
                } else {
                    $user->addGroup($parsedJwt->{$claims[JWTProperties::Groups->value]});
                }
            }
            $user->store();
            $this->storeToken($accessToken);
            if (!empty($refreshToken)) {
                $this->storeToken($refreshToken, self::REFRESH_TOKEN_COOKIE, $oidc->getRefreshExpiry());
            }
        }
        return $tokenIsValid;
    }

    public function getUsername(): string
    {
        return $this->username ?? '';
    }

    /**
     * @param string $token
     * @param string $tokenType
     * @param int|null $tokenDuration // TODO: Token expiration time (you should sync this with the token's actual expiration), e.g. a Refresh token does not need to be a JWT, so you can't read it in the same way all the time
     * @return void
     * @throws \byteShard\Exception
     */
    public function storeToken(string $token, string $tokenType = self::ACCESS_TOKEN_COOKIE, ?int $tokenDuration = 3600): void
    {
        if (is_null($tokenDuration)) {
            $tokenDuration = 3600;
        }
        if ($tokenType === self::ACCESS_TOKEN_COOKIE) {
            $jwt           = new JWT($token, $this->certPath);
            $tokenDuration = $jwt->tokenDuration();
        }
        setcookie($tokenType, $token, [
            'expires'  => time() + $tokenDuration,
            'secure'   => true,
            'httponly' => true,
            'samesite' => $tokenType === self::ACCESS_TOKEN_COOKIE ? 'Lax' : 'Strict', // Strict doesn't work with Firefox for the access token ¯\_(ツ)_/¯
            'path'     => '/',
        ]);
    }

}
