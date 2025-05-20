<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

use byteShard\Enum\LogLevel;
use JsonSerializable;

class Password implements JsonSerializable
{
    /** @var string */
    private string $algorithm = PASSWORD_DEFAULT;
    /** @var int */
    private int $cost = 12;

    /**
     * Don't create a setter for this property, don't set it in the constructor.
     * This is the only (known) way to prevent password leakage in stack traces in all possible circumstances.
     * @var string
     */
    public string $password;

    /**
     * @return string
     */
    public function __toString(): string
    {
        if (!isset($this->password)) {
            return '';
        }
        return $this->password;
    }

    public function __debugInfo(): array
    {
        if (defined('DISCLOSE_CREDENTIALS') && DISCLOSE_CREDENTIALS === true && defined('LOGLEVEL') && LOGLEVEL === LogLevel::DEBUG) {
            return get_object_vars($this);
        }
        $debug_info['password'] = !isset($this->password) ? '' : 'CONFIDENTIAL';
        return $debug_info;
    }

    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    /**
     * @param string $name
     * @return string|null
     */
    public static function getSecretString(string $name): ?string
    {
        $env = getenv($name);
        if ($env !== false) {
            return $env;
        }
        if (file_exists('/run/secrets/'.$name)) {
            $content = file_get_contents('/run/secrets/'.$name);
            return $content === false ? null : $content;
        }
        return null;
    }

    /**
     * @param string $name
     * @return Password|null
     * @API
     */
    public static function getSecret(string $name): ?Password
    {
        $password = new Password();
        $secret   = Password::getSecretString($name);
        if ($secret !== null) {
            $password->password = $secret;
            return $password;
        }
        return null;
    }

    /**
     * @param int $nrOfCharacters
     * @param int $nrOfNumbers
     * @param int $nrOfSymbols
     * @return ?Password
     * @throws \Exception
     */
    public static function getPassword(int $nrOfCharacters = 15, int $nrOfNumbers = 4, int $nrOfSymbols = 2): ?Password
    {
        $recipe   = array('a' => $nrOfCharacters, 'n' => $nrOfNumbers, 's' => $nrOfSymbols);
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers  = '1234567890';
        $symbols  = '!@#%^&*()_+-=[]{}|';
        $pass     = [];

        $a_len = strlen($alphabet) - 1;
        $n_len = strlen($numbers) - 1;
        $s_len = strlen($symbols) - 1;

        $total_len = $recipe['a'] + $recipe['n'] + $recipe['s'];

        // start with a character in case at least one character is in the mix
        if ($recipe['a'] > 0) {
            $pass[] = $alphabet[random_int(0, $a_len)];
            $recipe['a']--;
        }

        while (count($pass) < $total_len) {
            $type = random_int(1, $total_len);
            if ($type < $nrOfCharacters) {
                if ($recipe['a'] > 0) {
                    $pass[] = $alphabet[random_int(0, $a_len)];
                    $recipe['a']--;
                }
            } elseif ($type < ($nrOfCharacters + $nrOfNumbers)) {
                if ($recipe['n'] > 0) {
                    $pass[] = $numbers[random_int(0, $n_len)];
                    $recipe['n']--;
                }
            } else {
                if ($recipe['s'] > 0) {
                    $pass[] = $symbols[random_int(0, $s_len)];
                    $recipe['s']--;
                }
            }
        }
        if (!empty($pass)) {
            $password           = new Password();
            $password->password = implode($pass);
            return $password;
        }
        return null;
    }

    /**
     * @param string|null $algorithm
     * @param int|null $cost
     * @param string|null $salt
     * @return string|null
     */
    final public function hash(?string $algorithm = null, ?int $cost = null, ?string $salt = null): ?string
    {
        $options['cost'] = $cost ?? $this->cost;
        if ($salt !== null) {
            $options['salt'] = $salt;
        }
        return password_hash($this->password, $algorithm ?? $this->algorithm, $options);
    }
}
