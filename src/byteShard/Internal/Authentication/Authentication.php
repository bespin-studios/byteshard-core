<?php

namespace byteShard\Internal\Authentication;

use BackedEnum;
use byteShard\DataModelInterface;
use byteShard\Environment;
use byteShard\Internal\Authentication\Provider\Ldap;
use byteShard\Internal\Authentication\Provider\Local;
use byteShard\Internal\Authentication\Provider\Oauth;
use byteShard\Internal\ErrorHandler;
use byteShard\Internal\Server;
use byteShard\Internal\Session;

class Authentication
{
    public function __construct(
        private readonly ?Session           $session,
        private readonly Environment        $environment,
        private readonly bool               $serviceMode,
        private readonly int                $sessionTimeoutInMinutes,
        private readonly string             $logoffButtonName,
        private readonly DataModelInterface $dataModel
    )
    {
    }

    private function getIdentityProvider(): ?ProviderInterface
    {
        $referer = rtrim($_SERVER['HTTP_REFERER'], '/').'/';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_contains($referer, '/login/')) {
            $loginTemplate = $this->environment->getLoginTemplate();
            // login button was clicked on the login page
            if (array_key_exists($loginTemplate->getLoginButtonName(), $_POST)) {
                switch (Providers::tryFrom($_POST[$loginTemplate->getLoginButtonName()])) {
                    case Providers::LOCAL:
                        self::setAuthenticationProviderCookie(Providers::LOCAL);
                        return new Local(dataModel: $this->dataModel, authenticationObject: $this->environment->getLocalProvider());
                    case Providers::LDAP:
                        self::setAuthenticationProviderCookie(Providers::LDAP);
                        return new Ldap(authenticationObject: $this->environment->getLdapProvider());
                    case Providers::OAUTH:
                        self::setAuthenticationProviderCookie(Providers::OAUTH);
                        return new Oauth(provider: $this->environment->getOauthProvider(), certPath: $this->environment->getJwksCertPath());
                }
            }
            //TODO: forget pass, change pass etc
        }

        // subsequent requests have to use the same auth provider to check session state
        if (array_key_exists('auth', $_COOKIE)) {
            switch (Providers::tryFrom($_COOKIE['auth'])) {
                case Providers::LOCAL:
                    return new Local(dataModel: $this->dataModel);
                case Providers::LDAP:
                    return new Ldap();
                case Providers::OAUTH:
                    return new Oauth(certPath: $this->environment->getJwksCertPath());
            }
        }
        return null;
    }

    public function authenticate(): void
    {
        $GLOBALS['error_handler']->setResultObject(ErrorHandler::RESULT_OBJECT_LOGIN); //in case of error display a cell content error and don't redirect to log in
        $identityProvider = $this->getIdentityProvider();
        if ($identityProvider === null) {
            self::logout();
        }
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === $this->logoffButtonName) {
            self::logout($identityProvider);
        }

        $activeSession = $identityProvider->userHasValidAndNotExpiredSession($this->sessionTimeoutInMinutes);

        if ($activeSession === false) {
            $activeSession = $identityProvider->authenticate($this->environment->getLoginTemplate()->getCredentials() ?? null);
            if ($activeSession === true) {
                //TODO: check if this can be done another way. it only needs to be executed once after successful login, but for example oauth does it's initial authenticate on public/login/oauth.php
                $this->environment->processSuccessfulLogin($identityProvider->getUsername());
            }
        }

        // putting the check for service mode after authentication saves us from checking service mode during authentication
        if ($this->serviceMode === true && $this->dataModel->isServiceAccount(\byteShard\Session::getUserId()) === false) {
            self::logout($identityProvider);
        }
        if ($this->session === null) {
            self::logout($identityProvider);
        }
        if ($activeSession === false) {
            self::logout($identityProvider);
        }

        $this->environment->initializeUserCallback();
    }

    public static function setAuthenticationProviderCookie(Providers $provider): void
    {
        if (!array_key_exists('auth', $_COOKIE) || $_COOKIE['auth'] !== $provider->value) {
            setcookie('auth', $provider->value, [
                'expires'  => time() + 15552000,
                'secure'   => true,
                'httponly' => true,
                'path'     => '/'
            ]);
        }
    }

    public static function logout($identityProvider = null, array $parameters = []): never
    {
        $getParameters = '';
        if (!empty($parameters)) {
            $params = [];
            foreach ($parameters as $parameter => $value) {
                if ($value instanceof BackedEnum) {
                    $params[$parameter] = $value->value;
                } elseif (is_scalar($value)) {
                    $params[$parameter] = $value;
                }
            }
            $getParameters = '/?'.http_build_query($params);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        setcookie('auth', '', time() - 3600, '/');
        setcookie('PHPSESSID', '', time() - 3600, '/');
        header('Location: '.Server::getBaseUrl().'/login'.$getParameters);
        $identityProvider?->logout();
        exit;
    }
}