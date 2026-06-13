<?php

namespace App\Turn;

use RuntimeException;

/**
 * STUN/TURN binary message codec — RFC 5389 + RFC 5766.
 *
 * Attribute keying: $attributes is keyed by type (int). A second attribute of
 * the same type silently overwrites the first — malformed but acceptable for POC.
 */
class StunMessage
{
    const MAGIC_COOKIE = 0x2112A442;

    // Message types
    const BINDING_REQUEST = 0x0001;

    const BINDING_RESPONSE = 0x0101;

    const ALLOCATE_REQUEST = 0x0003;

    const ALLOCATE_RESPONSE = 0x0103;

    const ALLOCATE_ERROR = 0x0113;

    const REFRESH_REQUEST = 0x0004;

    const REFRESH_RESPONSE = 0x0104;

    const CREATE_PERMISSION_REQUEST = 0x0008;

    const CREATE_PERMISSION_RESPONSE = 0x0108;

    const SEND_INDICATION = 0x0016;

    const DATA_INDICATION = 0x0017;

    // Attribute types
    const ATTR_USERNAME = 0x0006;

    const ATTR_MESSAGE_INTEGRITY = 0x0008;

    const ATTR_ERROR_CODE = 0x0009;

    const ATTR_LIFETIME = 0x000D;

    const ATTR_XOR_PEER_ADDRESS = 0x0012;

    const ATTR_DATA = 0x0013;

    const ATTR_REALM = 0x0014;

    const ATTR_NONCE = 0x0015;

    const ATTR_XOR_RELAYED_ADDRESS = 0x0016;

    const ATTR_REQUESTED_TRANSPORT = 0x0019;

    const ATTR_XOR_MAPPED_ADDRESS = 0x0020;

    const ATTR_SOFTWARE = 0x8022;

    const ATTR_FINGERPRINT = 0x8028;

    public int $type = 0;

    public string $transactionId = ''; // 12 raw bytes

    /** @var array<int, mixed> */
    public array $attributes = [];

    public static function parse(string $buffer): self
    {
        if (strlen($buffer) < 20) {
            throw new RuntimeException('STUN buffer too short ('.strlen($buffer).' bytes)');
        }

        $msg = new self;

        $header = unpack('ntype/nlength/Ncookie/a12txn', $buffer);

        if ($header['cookie'] !== self::MAGIC_COOKIE) {
            throw new RuntimeException(sprintf('Invalid STUN magic cookie: 0x%08X', $header['cookie']));
        }

        $msg->type = $header['type'];
        $msg->transactionId = $header['txn'];

        $offset = 20;
        $end = 20 + $header['length'];

        while ($offset < $end && $offset < strlen($buffer)) {
            if ($offset + 4 > strlen($buffer)) {
                break;
            }

            $attrHeader = unpack('ntype/nlength', substr($buffer, $offset, 4));
            $attrType = $attrHeader['type'];
            $attrLen = $attrHeader['length'];
            $offset += 4;

            if ($offset + $attrLen > strlen($buffer)) {
                break;
            }

            $value = substr($buffer, $offset, $attrLen);

            $msg->attributes[$attrType] = match ($attrType) {
                self::ATTR_USERNAME,
                self::ATTR_REALM,
                self::ATTR_NONCE,
                self::ATTR_SOFTWARE => $value,
                self::ATTR_LIFETIME => unpack('Nval', $value)['val'],
                self::ATTR_REQUESTED_TRANSPORT => ord($value[0]),
                self::ATTR_MESSAGE_INTEGRITY,
                self::ATTR_DATA,
                self::ATTR_FINGERPRINT => $value,
                self::ATTR_XOR_MAPPED_ADDRESS,
                self::ATTR_XOR_RELAYED_ADDRESS,
                self::ATTR_XOR_PEER_ADDRESS => self::decodeXorAddress($value, $msg->transactionId),
                self::ATTR_ERROR_CODE => self::decodeErrorCode($value),
                default => $value,
            };

            // Attributes are padded to 4-byte boundaries
            $offset += $attrLen + ((4 - ($attrLen % 4)) % 4);
        }

        return $msg;
    }

    public function serialize(): string
    {
        $body = '';

        foreach ($this->attributes as $type => $value) {
            $encoded = $this->encodeAttribute($type, $value);
            $len = strlen($encoded);
            $pad = (4 - ($len % 4)) % 4;
            $body .= pack('nn', $type, $len).$encoded.str_repeat("\x00", $pad);
        }

        return pack('nnNa12', $this->type, strlen($body), self::MAGIC_COOKIE, $this->transactionId).$body;
    }

    public function getAttribute(int $type): mixed
    {
        return $this->attributes[$type] ?? null;
    }

    public function addXorMappedAddress(string $ip, int $port): void
    {
        $this->attributes[self::ATTR_XOR_MAPPED_ADDRESS] = ['ip' => $ip, 'port' => $port];
    }

    public function addXorRelayedAddress(string $ip, int $port): void
    {
        $this->attributes[self::ATTR_XOR_RELAYED_ADDRESS] = ['ip' => $ip, 'port' => $port];
    }

    public function addErrorCode(int $code, string $reason): void
    {
        $this->attributes[self::ATTR_ERROR_CODE] = ['code' => $code, 'reason' => $reason];
    }

    public function addRealm(string $realm): void
    {
        $this->attributes[self::ATTR_REALM] = $realm;
    }

    public function addNonce(string $nonce): void
    {
        $this->attributes[self::ATTR_NONCE] = $nonce;
    }

    public function addLifetime(int $seconds): void
    {
        $this->attributes[self::ATTR_LIFETIME] = $seconds;
    }

    public function addSoftware(string $software): void
    {
        $this->attributes[self::ATTR_SOFTWARE] = $software;
    }

    /**
     * Appends MESSAGE-INTEGRITY to the message.
     *
     * RFC 5389 §15.4 requires the header `Length` to be pre-adjusted to include
     * the MESSAGE-INTEGRITY attribute (4-byte attr header + 20-byte HMAC = 24 bytes)
     * BEFORE computing the HMAC, even though the attribute is not yet in the buffer.
     * This method handles that pre-adjustment internally.
     */
    public function addMessageIntegrity(string $key): void
    {
        // Serialise current attributes (without MESSAGE-INTEGRITY)
        $body = $this->serializeAttributesExcluding(self::ATTR_MESSAGE_INTEGRITY);

        // Pre-adjust Length to include MESSAGE-INTEGRITY attr (24 bytes: 4 header + 20 HMAC)
        $adjustedLength = strlen($body) + 24;

        $header = pack('nnNa12', $this->type, $adjustedLength, self::MAGIC_COOKIE, $this->transactionId);

        $hmac = hash_hmac('sha1', $header.$body, $key, true);

        $this->attributes[self::ATTR_MESSAGE_INTEGRITY] = $hmac;
    }

    /**
     * Verifies MESSAGE-INTEGRITY.
     *
     * Reconstructs the byte range that was signed: everything up to (not including)
     * the MESSAGE-INTEGRITY attribute, with the header Length pre-adjusted as in
     * RFC 5389 §15.4.
     */
    public function verifyMessageIntegrity(string $key): bool
    {
        if (! isset($this->attributes[self::ATTR_MESSAGE_INTEGRITY])) {
            return false;
        }

        $body = $this->serializeAttributesExcluding(self::ATTR_MESSAGE_INTEGRITY);
        $adjustedLength = strlen($body) + 24;
        $header = pack('nnNa12', $this->type, $adjustedLength, self::MAGIC_COOKIE, $this->transactionId);

        $expected = hash_hmac('sha1', $header.$body, $key, true);

        return hash_equals($expected, $this->attributes[self::ATTR_MESSAGE_INTEGRITY]);
    }

    /**
     * Derives the long-term credential HMAC key.
     *
     * RFC 5389 §15.4: key = MD5(username ":" realm ":" password) — binary digest (not hex).
     */
    public static function makeKey(string $username, string $realm, string $password): string
    {
        return md5($username.':'.$realm.':'.$password, true);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function serializeAttributesExcluding(int $excludeType): string
    {
        $body = '';

        foreach ($this->attributes as $type => $value) {
            if ($type === $excludeType) {
                continue;
            }

            $encoded = $this->encodeAttribute($type, $value);
            $len = strlen($encoded);
            $pad = (4 - ($len % 4)) % 4;
            $body .= pack('nn', $type, $len).$encoded.str_repeat("\x00", $pad);
        }

        return $body;
    }

    /** @return array{ip: string, port: int} */
    private static function decodeXorAddress(string $value, string $txnId): array
    {
        // Byte 0: reserved; Byte 1: family (0x01=IPv4)
        $family = ord($value[1]);

        if ($family !== 0x01) {
            return ['ip' => '0.0.0.0', 'port' => 0];
        }

        $xorPort = unpack('nport', substr($value, 2, 2))['port'];
        $xorIp = unpack('Nip', substr($value, 4, 4))['ip'];

        // Port: XOR with high 16 bits of magic cookie (0x2112)
        $port = $xorPort ^ 0x2112;

        // IP: XOR with full 32-bit magic cookie
        $ip = long2ip($xorIp ^ self::MAGIC_COOKIE);

        return ['ip' => $ip, 'port' => $port];
    }

    private static function encodeXorAddress(string $ip, int $port): string
    {
        // Port XOR with high 16 bits of magic cookie
        $xorPort = $port ^ 0x2112;

        // IP XOR with full 32-bit magic cookie
        $xorIp = ip2long($ip) ^ self::MAGIC_COOKIE;

        return "\x00\x01".pack('nN', $xorPort, $xorIp);
    }

    /** @return array{code: int, reason: string} */
    private static function decodeErrorCode(string $value): array
    {
        $class = ord($value[2]) & 0x07;
        $number = ord($value[3]);
        $code = $class * 100 + $number;
        $reason = substr($value, 4);

        return ['code' => $code, 'reason' => $reason];
    }

    private static function encodeErrorCode(int $code, string $reason): string
    {
        $class = intdiv($code, 100) & 0x07;
        $number = $code % 100;

        return "\x00\x00".chr($class).chr($number).$reason;
    }

    private function encodeAttribute(int $type, mixed $value): string
    {
        return match ($type) {
            self::ATTR_USERNAME,
            self::ATTR_REALM,
            self::ATTR_NONCE,
            self::ATTR_SOFTWARE => (string) $value,
            self::ATTR_LIFETIME => pack('N', (int) $value),
            self::ATTR_REQUESTED_TRANSPORT => chr((int) $value)."\x00\x00\x00",
            self::ATTR_MESSAGE_INTEGRITY,
            self::ATTR_DATA => (string) $value,
            self::ATTR_FINGERPRINT => (string) $value,
            self::ATTR_XOR_MAPPED_ADDRESS,
            self::ATTR_XOR_RELAYED_ADDRESS,
            self::ATTR_XOR_PEER_ADDRESS => self::encodeXorAddress($value['ip'], $value['port']),
            self::ATTR_ERROR_CODE => self::encodeErrorCode($value['code'], $value['reason']),
            default => (string) $value,
        };
    }
}
