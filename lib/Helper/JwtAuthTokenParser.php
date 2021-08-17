<?php
declare(strict_types=1);

namespace OCA\JwtAuth\Helper;
use Firebase\JWT\JWT;

class JwtAuthTokenParser
{
    public function __construct()
    {
    }

    public function parseValidatedToken(string $token, string $publicKeyPath, string $jwtAlg): ?string
    {
        $payload = get_object_vars(JWT::decode($token, $this->getPublicKey($publicKeyPath), array($jwtAlg)));
        if (is_null($payload['entryUUID'])) {
            return null;
        }
        return $payload['entryUUID'];
    }

    private function getPublicKey(string $public_key_path)
    {
        if (!is_null($public_key_path)) {
            return file_get_contents($public_key_path);
        } else {
            die("Can`t load public key to verify JWT token");
        }
    }


}
