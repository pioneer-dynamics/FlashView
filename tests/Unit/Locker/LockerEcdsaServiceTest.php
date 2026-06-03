<?php

namespace Tests\Unit\Locker;

use App\Services\LockerEcdsaService;
use PHPUnit\Framework\TestCase;

class LockerEcdsaServiceTest extends TestCase
{
    private LockerEcdsaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LockerEcdsaService;
    }

    public function test_public_key_to_pem_produces_valid_pem(): void
    {
        [$jwkBase64, $privateKeyPem] = $this->generateP256TestKeypair();
        $pem = $this->service->publicKeyToPem($jwkBase64);

        $this->assertStringStartsWith('-----BEGIN PUBLIC KEY-----', $pem);
        $this->assertStringEndsWith("-----END PUBLIC KEY-----\n", $pem);

        $key = openssl_pkey_get_public($pem);
        $this->assertNotFalse($key, 'OpenSSL could not load the generated PEM');
    }

    public function test_p1363_to_der_converts_known_r_s(): void
    {
        // Craft a P1363 signature with known r and s values (all 0x01 and 0x02)
        $r = str_repeat("\x01", 32);
        $s = str_repeat("\x02", 32);
        $p1363 = $r.$s;

        $der = $this->service->p1363ToDer($p1363);

        // DER sequence tag
        $this->assertEquals("\x30", $der[0]);
        // Must contain two INTEGER elements
        $this->assertGreaterThan(68, strlen($der));
        // r integer tag
        $this->assertEquals("\x02", $der[2]);
    }

    public function test_p1363_to_der_prepends_zero_for_high_bit_r(): void
    {
        // r with high bit set requires 0x00 prepend in DER
        $r = "\x80".str_repeat("\x01", 31);
        $s = str_repeat("\x02", 32);
        $p1363 = $r.$s;

        $der = $this->service->p1363ToDer($p1363);

        // r should be 34 bytes (0x00 + 0x80 + 31 bytes)
        $rLen = ord($der[3]);
        $this->assertEquals(33, $rLen, 'r with high bit should be padded with 0x00');
        $this->assertEquals("\x00", $der[4], 'First byte of padded r should be 0x00');
    }

    public function test_verify_returns_true_for_valid_signature(): void
    {
        [$publicKeyJwkBase64, $privateKeyPem] = $this->generateP256TestKeypair();

        $challengeHex = bin2hex(random_bytes(32));
        $message = hex2bin($challengeHex);

        // Sign using OpenSSL (DER format)
        openssl_sign($message, $derSignature, $privateKeyPem, OPENSSL_ALGO_SHA256);

        // Convert DER to P1363 for testing
        $signatureBase64 = base64_encode($this->derToP1363($derSignature));

        $result = $this->service->verify($publicKeyJwkBase64, $challengeHex, $signatureBase64);
        $this->assertTrue($result);
    }

    public function test_verify_returns_false_for_tampered_signature(): void
    {
        [$publicKeyJwkBase64] = $this->generateP256TestKeypair();

        $challengeHex = bin2hex(random_bytes(32));
        $fakeSignature = base64_encode(str_repeat("\x00", 64));

        $result = $this->service->verify($publicKeyJwkBase64, $challengeHex, $fakeSignature);
        $this->assertFalse($result);
    }

    public function test_verify_returns_false_for_invalid_public_key(): void
    {
        $invalidJwkBase64 = base64_encode('not-valid-json');
        $challengeHex = bin2hex(random_bytes(32));
        $fakeSignature = base64_encode(str_repeat("\x00", 64));

        $result = $this->service->verify($invalidJwkBase64, $challengeHex, $fakeSignature);
        $this->assertFalse($result);
    }

    /**
     * Generate a real P-256 keypair using OpenSSL. Returns [publicKeyJwkBase64, privateKeyPem].
     *
     * @return array{0: string, 1: string}
     */
    private function generateP256TestKeypair(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $details = openssl_pkey_get_details($key);
        openssl_pkey_export($key, $privateKeyPem);

        $ecPoint = $details['ec'];
        $x = base64_encode($ecPoint['x']);
        $y = base64_encode($ecPoint['y']);

        // Convert to base64url
        $toBase64url = fn ($b64) => rtrim(strtr($b64, '+/', '-_'), '=');

        $jwk = ['kty' => 'EC', 'crv' => 'P-256', 'x' => $toBase64url($x), 'y' => $toBase64url($y)];
        $publicKeyJwkBase64 = base64_encode(json_encode($jwk));

        return [$publicKeyJwkBase64, $privateKeyPem];
    }

    /**
     * Convert a DER-encoded P-256 ECDSA signature to IEEE P1363 (r||s, 32+32 bytes).
     */
    private function derToP1363(string $der): string
    {
        // Skip SEQUENCE tag and length (bytes 0-1)
        $offset = 2;
        // r INTEGER
        $offset++; // skip 0x02 tag
        $rLen = ord($der[$offset++]);
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;
        // s INTEGER
        $offset++; // skip 0x02 tag
        $sLen = ord($der[$offset++]);
        $s = substr($der, $offset, $sLen);

        // Strip leading zero if present, then pad to 32 bytes
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

        return $r.$s;
    }
}
