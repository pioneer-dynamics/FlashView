<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LockerEcdsaService
{
    public function issueChallenge(string $accountId): array
    {
        $challengeHex = bin2hex(random_bytes(32));
        $challengeId = Str::uuid()->toString();
        Cache::put(
            "locker-signing-challenge:{$challengeId}",
            ['account_id' => $accountId, 'challenge' => $challengeHex],
            60
        );

        return ['challenge_id' => $challengeId, 'challenge' => $challengeHex];
    }

    public function consumeChallenge(string $challengeId, string $expectedAccountId): ?string
    {
        // Cache::pull() is atomic get+delete — prevents replay under concurrent requests (Octane)
        $data = Cache::pull("locker-signing-challenge:{$challengeId}");
        if (! $data || $data['account_id'] !== $expectedAccountId) {
            return null;
        }

        return $data['challenge'];
    }

    public function verify(string $publicKeyJwkBase64, string $challengeHex, string $signatureBase64): bool
    {
        try {
            $pem = $this->publicKeyToPem($publicKeyJwkBase64);
        } catch (\Throwable) {
            return false;
        }
        $pubKey = openssl_pkey_get_public($pem);
        if (! $pubKey) {
            return false;
        }

        $sigDer = $this->p1363ToDer(base64_decode($signatureBase64));
        $message = hex2bin($challengeHex);

        return openssl_verify($message, $sigDer, $pubKey, OPENSSL_ALGO_SHA256) === 1;
    }

    public function publicKeyToPem(string $jwkBase64): string
    {
        $jwk = json_decode(base64_decode($jwkBase64), true);
        if (! is_array($jwk) || ! isset($jwk['x'], $jwk['y'])) {
            throw new \InvalidArgumentException('Invalid JWK: missing x or y coordinates');
        }
        $x = $this->base64urlToBytes($jwk['x']);
        $y = $this->base64urlToBytes($jwk['y']);

        // Pad x and y to 32 bytes
        $x = str_pad($x, 32, "\x00", STR_PAD_LEFT);
        $y = str_pad($y, 32, "\x00", STR_PAD_LEFT);

        $uncompressed = "\x04".$x.$y;

        // OID for id-ecPublicKey: 1.2.840.10045.2.1
        $ecPubKeyOid = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01";
        // OID for P-256 (secp256r1 / prime256v1): 1.2.840.10045.3.1.7
        $p256Oid = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

        $algorithmIdentifier = "\x30".chr(strlen($ecPubKeyOid) + strlen($p256Oid))
            .$ecPubKeyOid.$p256Oid;
        $bitString = "\x03".chr(strlen($uncompressed) + 1)."\x00".$uncompressed;
        $spki = "\x30".chr(strlen($algorithmIdentifier) + strlen($bitString))
            .$algorithmIdentifier.$bitString;

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($spki), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    public function p1363ToDer(string $p1363): string
    {
        // P1363 = 64 bytes: 32 bytes r || 32 bytes s
        $r = substr($p1363, 0, 32);
        $s = substr($p1363, 32, 32);

        $r = ltrim($r, "\x00");
        if ($r === '') {
            $r = "\x00";
        }
        if (ord($r[0]) > 0x7F) {
            $r = "\x00".$r;
        }

        $s = ltrim($s, "\x00");
        if ($s === '') {
            $s = "\x00";
        }
        if (ord($s[0]) > 0x7F) {
            $s = "\x00".$s;
        }

        $intR = "\x02".chr(strlen($r)).$r;
        $intS = "\x02".chr(strlen($s)).$s;
        $seq = $intR.$intS;

        return "\x30".chr(strlen($seq)).$seq;
    }

    private function base64urlToBytes(string $base64url): string
    {
        $base64 = strtr($base64url, '-_', '+/');
        $pad = strlen($base64) % 4;
        if ($pad) {
            $base64 .= str_repeat('=', 4 - $pad);
        }

        return base64_decode($base64);
    }
}
