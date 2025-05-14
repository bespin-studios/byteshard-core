<?php

namespace byteShard\Authentication;

use byteShard\Internal\Authentication\Providers;
use byteShard\Internal\Config;
use byteShard\Jwt;

class User
{
    const USER_TOKEN_COOKIE_NAME = 'user_token';
    private static ?User   $instance = null;
    private static ?Config $config   = null;

    private function __construct(
        private string    $username = '',
        private string    $firstname = '',
        private string    $lastname = '',
        private string    $mail = '',
        /**
         * @var array<string>
         */
        private array     $groups = [],
        private Providers $provider = Providers::LOCAL,
    ) {
    }

    /**
     * Creates a new User instance, typically for a new user before they are stored/logged in.
     * This instance is not automatically set as the global singleton until store() is called on it.
     *
     * @param string $username
     * @param string $firstname
     * @param string $lastname
     * @param string $mail
     * @param array<string> $initialGroups
     * @param Providers $provider
     * @return User
     */
    public static function createUser(
        string    $username,
        string    $firstname,
        string    $lastname,
        string    $mail,
        array     $initialGroups = [],
        Providers $provider = Providers::LOCAL
    ): User {
        return new self(
            $username,
            $firstname,
            $lastname,
            $mail,
            $initialGroups,
            $provider
        );
    }

    private static function getConfig(): ?object
    {
        if (self::$config !== null) {
            return self::$config;
        }
        if (class_exists('\\config')) {
            $customConfig = new \config();
            if ($customConfig instanceof Config) {
                self::$config = $customConfig;
            }
        }
        return self::$config;
    }

    public static function getUserData(): ?User
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (!isset($_COOKIE[self::USER_TOKEN_COOKIE_NAME])) {
            return null;
        }

        try {
            $jwt            = $_COOKIE[self::USER_TOKEN_COOKIE_NAME];
            $decoded        = Jwt::decode(self::getConfig(), $jwt);
            self::$instance = new self(
                $decoded['username'],
                $decoded['firstname'],
                $decoded['lastname'],
                $decoded['mail'],
                (array)$decoded['groups'],
                Providers::tryFrom($decoded['provider']) ?? Providers::LOCAL
            );
        } catch (\Exception $e) {
            // Log the error, e.g., invalid token, expired token
            // For now, we'll return null, effectively logging the user out or treating them as not logged in.
            self::logout(); // It might be good to clear a potentially invalid cookie
            return null;
        }

        return self::$instance;
    }

    public static function logout(): void
    {
        setcookie(self::USER_TOKEN_COOKIE_NAME, '', time() - 3600, "/", "", true, true);
        self::$instance = null;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function setFirstname(string $firstname): void
    {
        $this->firstname = $firstname;
    }

    public function setLastname(string $lastname): void
    {
        $this->lastname = $lastname;
    }

    public function setMail(string $mail): void
    {
        $this->mail = $mail;
    }

    public function setProvider(Providers $provider): void
    {
        $this->provider = $provider;
    }

    public function addGroup(string $group, bool $ldap = false): void
    {
        if ($ldap) {
            if (preg_match('/cn=([^,]+)/i', $group, $matches)) {
                $this->groups[] = $matches[1];
            }
        } else {
            $this->groups[] = $group;
        }
    }

    public function store(): bool
    {
        $payload = [
            'username'  => $this->username,
            'firstname' => $this->firstname,
            'lastname'  => $this->lastname,
            'mail'      => $this->mail,
            'provider'  => $this->provider->value,
            'groups'    => $this->groups,
            'iat'       => time(),
            'exp'       => time() + 3600
        ];

        try {
            $jwt = Jwt::create(self::getConfig(), $payload);
            setcookie(self::USER_TOKEN_COOKIE_NAME, $jwt, time() + 3600, "/", "", true, true);
            // Update the static instance to reflect the current state
            self::$instance = $this;
            return true;
        } catch (\Exception $e) {
            // Log the error during JWT encoding
            return false;
        }
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function getMail(): string
    {
        return $this->mail;
    }

    public function getProvider(): Providers
    {
        return $this->provider;
    }

    /**
     * @return array<string>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

}