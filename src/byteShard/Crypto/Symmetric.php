<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Crypto;

use byteShard\Crypto;
use Exception;
use SodiumException;

class Symmetric
{
    private static string $hashAlgorithm   = 'sha256';
    private static string $cypherAlgorithm = 'aes-128-ctr';
    private static int    $saltLength      = 0;
    private static bool   $useSodium       = true;

    /**
     * @API
     */
    public static function setSaltLength(int $saltLength): void
    {
        self::$saltLength = $saltLength;
    }

    /**
     * @API
     */
    public static function setHashAlgorithm(string $hashAlgorithm): void
    {
        self::$hashAlgorithm = $hashAlgorithm;
    }

    /**
     * @API
     */
    public static function setCypherAlgorithm(string $cypherAlgorithm): void
    {
        self::$cypherAlgorithm = $cypherAlgorithm;
    }

    /**
     * Encrypts then MACs a message
     *
     * @param string $message - plaintext message
     * @param string $key - encryption key (raw binary expected)
     * @param boolean $encode - set to TRUE to return a base64-encoded string
     * @return string (raw binary)
     * @throws \byteShard\Exception
     * @throws SodiumException
     */
    public static function encrypt(string $message, string $key = '', bool $encode = true, string $nonce = ''): string
    {
        $salt = self::$saltLength > 0 ? Crypto::randomBytes(self::$saltLength) : '';

        list($encKey, $authKey) = self::splitKeys($key, $salt);

        if (self::$useSodium === true) {
            if ($nonce === '') {
                $nonce = Crypto::randomBytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            }
            $ciphertext = sodium_crypto_secretbox($message, $nonce, $key);
            if ($encode === true) {
                return self::base64encode($nonce.$ciphertext);
            }
            return $nonce.$ciphertext;
        } else {
            if ($nonce === '') {
                $nonce = Crypto::randomBytes(openssl_cipher_iv_length(self::$cypherAlgorithm));
            }
            $ciphertext = openssl_encrypt(
                $message,
                self::$cypherAlgorithm,
                $encKey,
                OPENSSL_RAW_DATA,
                $nonce
            );

            // Calculate a MAC of the IV and ciphertext
            $mac = hash_hmac(self::$hashAlgorithm, $nonce.$ciphertext, $authKey, true);

            if ($encode === true) {
                return self::base64encode($salt.$mac.$nonce.$ciphertext);
            }
            // Prepend MAC to the ciphertext and return to caller
            return $salt.$mac.$ciphertext;
        }
    }

    private static function base64encode(string $payload): string
    {
        // some WAFs check for certain patterns like /on.*=/ for injected js code like onclick= or onmouseover=
        // base64 appends = or == at the end of the string, depending on the length of the input
        // to prevent patterns which might be caught be WAFs we prepend blanks to the payload before encoding and trim the string after decoding
        // since the payload might start with a space we always prepend an escape character, in this case a #, to the string and then prepend additional spaces in front of it.
        // during decoding, we trim everything up to the first occurrence of #
        $payload = '#'.$payload;
        $rest    = strlen($payload) % 3;
        if ($rest > 0) {
            $payload = str_repeat(' ', 3 - $rest).$payload;
        }
        return base64_encode($payload);
    }

    /** @throws Exception */
    private static function base64decode(string $encoded): string
    {
        $message = base64_decode($encoded, true);
        if ($message === false) {
            throw new Exception('Encryption failure: invalid encoding');
        }
        $message = ltrim($message, ' ');
        if (strlen($message) === 0 || $message[0] !== '#') {
            throw new Exception('Encryption failure: invalid format');
        }
        return substr($message, 1);
    }

    /** @throws Exception */
    public static function checkNonce(string $message, string $nonce, bool $encoded = true): bool
    {
        if ($encoded === true) {
            $message = self::base64decode($message);
        }
        if (self::$useSodium) {
            $encNonce = mb_substr($message, 0, 24, '8bit');
            return $encNonce === $nonce;
        }
        //TODO: implement non lib sodium check
        return true;
    }

    /** @throws Exception */
    public static function getNonce(string $message, bool $encoded = true): string
    {
        if ($encoded === true) {
            $message = self::base64decode($message);
        }
        if (self::$useSodium) {
            return mb_substr($message, 0, 24, '8bit');
        }
        //TODO: implement non lib sodium check
        return '';
    }

    /**
     * derive nonce from a key which can be recomputed deterministically
     * @param string $key
     * @param string $purpose
     * @return string
     */
    public static function deriveNonce(string $key, string $purpose): string
    {
        return mb_substr(hash_hmac('sha256', $purpose, $key, true), 0, 24, '8bit');
    }

    /**
     * Decrypts a message (after verifying integrity)
     *
     * @param string $message - ciphertext message
     * @param string $key - encryption key (raw binary expected)
     * @param boolean $encoded - are we expecting an encoded string?
     * @return string (raw binary)
     * @throws SodiumException
     * @throws Exception
     */
    public static function decrypt(string $message, string $key = '', bool $encoded = true): string
    {
        if ($encoded === true) {
            $message = self::base64decode($message);
        }
        if (self::$useSodium) {
            $nonce  = mb_substr($message, 0, 24, '8bit');
            $cipher = mb_substr($message, 24, null, '8bit');
            return sodium_crypto_secretbox_open($cipher, $nonce, $key);
        }

        $salt    = mb_substr($message, 0, self::$saltLength, '8bit');
        $message = mb_substr($message, self::$saltLength, null, '8bit');
        list($encryptionKey, $authenticationKey) = self::splitKeys($key, $salt);

        // Hash Size -- in case hashAlgorithm is changed
        $hashSize = mb_strlen(hash(self::$hashAlgorithm, '', true), '8bit');
        $mac      = mb_substr($message, 0, $hashSize, '8bit');

        $ciphertext = mb_substr($message, $hashSize, null, '8bit');

        $calculated = hash_hmac(
            self::$hashAlgorithm,
            $ciphertext,
            $authenticationKey,
            true
        );

        if (!self::hashEquals($mac, $calculated)) {
            throw new Exception('Encryption failure');
        }

        $nonceSize = openssl_cipher_iv_length(self::$cypherAlgorithm);

        return openssl_decrypt(
            mb_substr($ciphertext, $nonceSize, null, '8bit'),
            self::$cypherAlgorithm,
            $encryptionKey,
            OPENSSL_RAW_DATA,
            mb_substr($ciphertext, 0, $nonceSize, '8bit')
        );
    }

    /**
     * Splits a key into two separate keys; one for encryption
     * and the other for authentication
     *
     * @param string $masterKey (raw binary)
     * @return array (two raw binary strings)
     */
    private static function splitKeys(string $masterKey, string $salt): array
    {
        return [
            hash_hkdf(self::$hashAlgorithm, $masterKey, 32, 'aes-256-encryption', $salt),
            hash_hkdf(self::$hashAlgorithm, $masterKey, 32, 'sha-256-authentication', $salt)
        ];
    }

    /**
     * Compare two strings without leaking timing information
     *
     * @param string $knownString
     * @param string $userString
     * @ref https://paragonie.com/b/WS1DLx6BnpsdaVQW
     * @return boolean
     * @throws \byteShard\Exception
     */
    private static function hashEquals(string $knownString, string $userString): bool
    {
        if (function_exists('hash_equals')) {
            return hash_equals($knownString, $userString);
        }
        $nonce = Crypto::randomBytes(32);
        return hash_hmac(self::$hashAlgorithm, $knownString, $nonce) === hash_hmac(self::$hashAlgorithm, $userString, $nonce);
    }
}