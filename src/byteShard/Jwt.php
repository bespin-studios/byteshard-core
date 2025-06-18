<?php

namespace byteShard;

use byteShard\Internal\Config;

class Jwt
{
    public static function validate(Config $config, string $jwt): bool
    {
        $parts = explode('.', $jwt);
        if (count($parts) === 3) {
            $publicKey = file_get_contents($config->getJwtPublicKeyPath());
            return openssl_verify($parts[0].'.'.$parts[1], self::base64urlDecode($parts[2]), $publicKey, $config->getJwtAlgorithm()) === 1;
        }
        return false;
    }

    /**
     * @param Config $config
     * @param string $jwt
     * @return array<string>|null
     */
    public static function decode(Config $config, string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        if (!self::validate($config, $jwt)) {
            return null;
        }
        return json_decode(self::base64urlDecode($parts[1]), true);
    }

    public static function create(Config $config, array $payload, array $header = []): string
    {
        if (empty($header)) {
            $header = ['typ' => 'JWT', 'alg' => 'RS256'];
        }
        $algo           = $config->getJwtAlgorithm();
        $privateKeyPath = $config->getJwtPrivateKeyPath();

        $encodedHeader  = self::base64urlEncode(json_encode($header));
        $encodedPayload = self::base64urlEncode(json_encode($payload));

        $signature = '';
        openssl_sign($encodedHeader.'.'.$encodedPayload, $signature, file_get_contents($privateKeyPath), $algo);
        $base64UrlSignature = self::base64urlEncode($signature);

        return $encodedHeader.".".$encodedPayload.".".$base64UrlSignature;
    }

    private static function base64urlEncode(string $string): string
    {
        return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $string): string
    {
        return base64_decode(strtr($string, '-_', '+/'));
    }
}
