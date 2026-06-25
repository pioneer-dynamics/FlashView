<?php

use App\Services\LockerEcdsaService;

beforeEach(function () {
    $this->service = new LockerEcdsaService;
});

test('public key to pem produces valid pem', function () {
    [$jwkBase64, $privateKeyPem] = generateP256TestKeypair();
    $pem = $this->service->publicKeyToPem($jwkBase64);

    expect($pem)->toStartWith('-----BEGIN PUBLIC KEY-----');
    expect($pem)->toEndWith("-----END PUBLIC KEY-----\n");

    $key = openssl_pkey_get_public($pem);
    $this->assertNotFalse($key, 'OpenSSL could not load the generated PEM');
});

test('p1363 to der converts known r s', function () {
    // Craft a P1363 signature with known r and s values (all 0x01 and 0x02)
    $r = str_repeat("\x01", 32);
    $s = str_repeat("\x02", 32);
    $p1363 = $r.$s;

    $der = $this->service->p1363ToDer($p1363);

    // DER sequence tag
    expect($der[0])->toEqual("\x30");

    // Must contain two INTEGER elements
    expect(strlen($der))->toBeGreaterThan(68);

    // r integer tag
    expect($der[2])->toEqual("\x02");
});

test('p1363 to der prepends zero for high bit r', function () {
    // r with high bit set requires 0x00 prepend in DER
    $r = "\x80".str_repeat("\x01", 31);
    $s = str_repeat("\x02", 32);
    $p1363 = $r.$s;

    $der = $this->service->p1363ToDer($p1363);

    // r should be 34 bytes (0x00 + 0x80 + 31 bytes)
    $rLen = ord($der[3]);
    expect($rLen)->toEqual(33, 'r with high bit should be padded with 0x00');
    expect($der[4])->toEqual("\x00", 'First byte of padded r should be 0x00');
});

test('verify returns true for valid signature', function () {
    [$publicKeyJwkBase64, $privateKeyPem] = generateP256TestKeypair();

    $challengeHex = bin2hex(random_bytes(32));
    $message = hex2bin($challengeHex);

    // Sign using OpenSSL (DER format)
    openssl_sign($message, $derSignature, $privateKeyPem, OPENSSL_ALGO_SHA256);

    // Convert DER to P1363 for testing
    $signatureBase64 = base64_encode(derToP1363($derSignature));

    $result = $this->service->verify($publicKeyJwkBase64, $challengeHex, $signatureBase64);
    expect($result)->toBeTrue();
});

test('verify returns false for tampered signature', function () {
    [$publicKeyJwkBase64] = generateP256TestKeypair();

    $challengeHex = bin2hex(random_bytes(32));
    $fakeSignature = base64_encode(str_repeat("\x00", 64));

    $result = $this->service->verify($publicKeyJwkBase64, $challengeHex, $fakeSignature);
    expect($result)->toBeFalse();
});

test('verify returns false for invalid public key', function () {
    $invalidJwkBase64 = base64_encode('not-valid-json');
    $challengeHex = bin2hex(random_bytes(32));
    $fakeSignature = base64_encode(str_repeat("\x00", 64));

    $result = $this->service->verify($invalidJwkBase64, $challengeHex, $fakeSignature);
    expect($result)->toBeFalse();
});

/**
 * Generate a real P-256 keypair using OpenSSL. Returns [publicKeyJwkBase64, privateKeyPem].
 *
 * @return array{0: string, 1: string}
 */
function generateP256TestKeypair(): array
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
function derToP1363(string $der): string
{
    // Skip SEQUENCE tag and length (bytes 0-1)
    $offset = 2;

    // r INTEGER
    $offset++;
    // skip 0x02 tag
    $rLen = ord($der[$offset++]);
    $r = substr($der, $offset, $rLen);
    $offset += $rLen;

    // s INTEGER
    $offset++;
    // skip 0x02 tag
    $sLen = ord($der[$offset++]);
    $s = substr($der, $offset, $sLen);

    // Strip leading zero if present, then pad to 32 bytes
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

    return $r.$s;
}
