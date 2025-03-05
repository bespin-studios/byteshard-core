<?php

namespace byteShard\Internal\Authentication;

use byteShard\Exception;
use byteShard\Password;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

class JWT
{
    private ?object $jwt;

    public function __construct(string $token, private readonly string $certPath)
    {
        list($headBase64, $bodyBase64, $cryptoBase64) = explode('.', $token);
        $header    = FirebaseJWT::jsonDecode(FirebaseJWT::urlsafeB64Decode($headBase64));
        $algorithm = $header->alg ?? 'RS256'; // Default to RS256 if not set
        $kid       = $header->kid ?? null;

        if (str_starts_with($algorithm, 'HS')) {
            // HMAC (HS256, HS384, HS512) uses shared secret
            $key = Password::getSecretString('keycloakClientSecret');
        } else {
            // RSA (RS256, RS384, RS512) uses JWKS
            $jwks = json_decode(file_get_contents($this->certPath), true);
            $key  = null;

            foreach ($jwks['keys'] as $k) {
                if (isset($k['kid']) && $k['kid'] === $kid) {
                    if (isset($k['x5c']) && count($k['x5c']) === 1) {
                        $key = $this->x5cToPem($k['x5c'][0]);
                    }
                    break;
                }
            }

            if (!$key) {
                throw new Exception("No matching key found in JWKS.");
            }
        }
        try {
            $this->jwt = FirebaseJWT::decode($token, new Key($key, $algorithm));
        } catch (ExpiredException $e) {
            $this->jwt = $e->getPayload();
        }
    }

    public function getPreferredUsername(): string
    {
        return $this->jwt->preferred_username;
    }

    public function getRealmAccessRoles(): array
    {
        if (isset($this->jwt->realm_access->roles) && is_array($this->jwt->realm_access->roles)) {
            return $this->jwt->realm_access->roles;
        }
        return [];
    }

    public function isTokenValid(): bool
    {
        if ($this->jwt === null) {
            return false;
        }
        if ($this->isTokenExpired() === true) {
            return false;
        }
        return true;
    }

    public function isTokenExpired(): bool
    {
        return time() > $this->jwt->exp;
    }

    public function tokenDuration(): int
    {
        return $this->jwt->exp - time();
    }

    public function getJwt(): ?object
    {
        return $this->jwt;
    }

    private function x5cToPem(string $x5c): string
    {
        $cert = "-----BEGIN CERTIFICATE-----\n";
        $cert .= chunk_split($x5c, 64, "\n");
        $cert .= "-----END CERTIFICATE-----\n";
        return $cert;
    }
}