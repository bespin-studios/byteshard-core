<?php

namespace byteShard\Internal\Authentication\Provider;

use byteShard\Authentication\User;
use byteShard\Internal\Authentication\Authentication;
use byteShard\Internal\Authentication\AuthenticationAction;
use byteShard\Internal\Authentication\LdapProviderInterface;
use byteShard\Internal\Authentication\ProviderInterface;
use byteShard\Internal\Authentication\Providers;
use byteShard\Internal\Login\Struct\Credentials;
use byteShard\Ldap\Enum\ResultObject;
use byteShard\Ldap\Filter;
use byteShard\Session;
use config;

class Ldap implements ProviderInterface
{
    private string $username;

    public function __construct(
        private readonly ?LdapProviderInterface $authenticationObject = null
    ) {
    }

    public function authenticate(?Credentials $credentials = null): bool
    {
        if ($credentials === null) {
            $error = AuthenticationAction::INVALID_CREDENTIALS;
            $error->processAction($this);
        }
        if ($this->authenticationObject !== null) {
            return $this->authenticateAgainstAppProvider($credentials);
        }
        return $this->authenticateAgainstDefaultProvider($credentials);
    }

    private function authenticateAgainstDefaultProvider(Credentials $credentials): bool
    {
        if (class_exists('\\config')) {
            $config     = new config();
            $ldapHost   = $config->getLdapUrl();
            $ldapPort   = $config->getLdapPort();
            $ldapMethod = $config->getLdapMethod();

            $ldap           = $this->getLdapInstance(host: $ldapHost, port: $ldapPort, method: $ldapMethod);
            $tecCredentials = new Credentials();
            $tecCredentials->setUsername($config->getLdapBindDn());
            $tecCredentials->setPassword($config->getLdapBindPass());
            $ldap->connect($tecCredentials);

            $loginUsername = $credentials->getUsername();

            $filter = new Filter($config->getLdapBaseDn());
            $filter->setFilter($config->getLdapUid().'='.$loginUsername);
            $users = $ldap->getArray(
                $filter,
                $config->getLdapAttributes(),
            );
            if (count($users) === 1) {
                $user = $users[0];
                $credentials->setUsername($user->dn);
                $authenticated = $this->getLdapInstance($ldapHost, $ldapPort, $ldapMethod)->authenticate($credentials);
                if ($authenticated === true) {
                    $userObject = User::createUser(
                        username: $user->{ResultObject::Username->value} ?? '',
                        firstname: $user->{ResultObject::Firstname->value} ?? '',
                        lastname: $user->{ResultObject::Lastname->value} ?? '',
                        mail: $user->{ResultObject::Mail->value} ?? '',
                        provider: Providers::LDAP
                    );
                    if (isset($user->{ResultObject::Groups->value})) {
                        if (is_array($user->{ResultObject::Groups->value})) {
                            foreach ($user->{ResultObject::Groups->value} as $group) {
                                $userObject->addGroup($this->parseGroupName($group));
                            }
                        } else {
                            $userObject->addGroup($this->parseGroupName($user->{ResultObject::Groups->value}));
                        }
                    }
                    $userObject->store();
                    $this->username = $loginUsername;
                    return true;
                }
            }
            // either the user hasn't been found or the ldap bind was not successful
            Authentication::logout(action: AuthenticationAction::INVALID_CREDENTIALS);
        }
        Authentication::logout();
    }

    private function parseGroupName(string $groupName): string
    {
        if (preg_match('/cn=([^,]+)/i', $groupName, $matches)) {
            return $matches[1];
        }
        return $groupName;
    }

    private function getLdapInstance(string $host, int $port = 389, string $method = ''): \byteShard\Ldap
    {
        $ldap = new \byteShard\Ldap($host, $port);
        if ($method === 'start_tls') {
            $ldap->useStartTLS();
        }
        return $ldap;
    }

    private function authenticateAgainstAppProvider(Credentials $credentials): bool
    {
        $result = $this->authenticationObject->authenticate($credentials);

        if ($result->isSuccess() === true) {
            $this->username = $credentials->getUsername();
            return true;
        }
        $action = $result->getAction();
        if ($action === null) {
            $action = AuthenticationAction::UNEXPECTED_ERROR;
        }
        $action->processAction($this);
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function userHasValidAndNotExpiredSession(int $sessionTimeoutInMinutes): bool
    {
        $loginState = Session::getLoginState();
        if ($loginState === true && (($sessionTimeoutInMinutes * 60) < (time() - Session::getTimeOfLastUserRequest()))) {
            return false; // Session timeout -> logout
        }
        return $loginState;
    }

    public function logout(): void
    {
        User::logout();
    }
}