<?php
declare(strict_types=1);

function webauthn_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function webauthn_b64url_decode(string $value): string
{
    $value = strtr($value, '-_', '+/');
    $pad = strlen($value) % 4;
    if ($pad !== 0) {
        $value .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode($value, true);
    if ($decoded === false) {
        throw new RuntimeException('Data base64url tidak valid.');
    }
    return $decoded;
}

function webauthn_rp_id(): string
{
    global $config;
    $configured = trim((string)($config['security']['webauthn_rp_id'] ?? ''));
    if ($configured !== '') {
        return strtolower($configured);
    }
    $host = parse_url((string)($config['app']['url'] ?? ''), PHP_URL_HOST);
    return strtolower((string)($host ?: ($_SERVER['HTTP_HOST'] ?? '')));
}

function webauthn_origin(): string
{
    global $config;
    $configured = rtrim((string)($config['security']['webauthn_origin'] ?? ''), '/');
    if ($configured !== '') {
        return $configured;
    }
    return rtrim((string)($config['app']['url'] ?? ''), '/');
}

function webauthn_new_challenge(string $purpose, int $userId, int $deviceId): string
{
    $challenge = webauthn_b64url_encode(random_bytes(32));
    $_SESSION['webauthn_challenge'] = [
        'challenge' => $challenge,
        'purpose' => $purpose,
        'user_id' => $userId,
        'device_id' => $deviceId,
        'expires_at' => time() + 300,
    ];
    return $challenge;
}

function webauthn_consume_challenge(string $purpose, int $userId, int $deviceId, string $challenge): void
{
    $stored = $_SESSION['webauthn_challenge'] ?? null;
    unset($_SESSION['webauthn_challenge']);
    if (!is_array($stored)
        || ($stored['purpose'] ?? '') !== $purpose
        || (int)($stored['user_id'] ?? 0) !== $userId
        || (int)($stored['device_id'] ?? 0) !== $deviceId
        || (int)($stored['expires_at'] ?? 0) < time()
        || !hash_equals((string)($stored['challenge'] ?? ''), $challenge)
    ) {
        throw new RuntimeException('Challenge biometrik tidak valid atau sudah kedaluwarsa.');
    }
}

function webauthn_read_uint(string $data, int &$offset, int $additional): int
{
    $length = strlen($data);
    if ($additional < 24) {
        return $additional;
    }
    $bytes = match ($additional) {
        24 => 1,
        25 => 2,
        26 => 4,
        27 => 8,
        default => throw new RuntimeException('CBOR indefinite length tidak didukung.'),
    };
    if ($offset + $bytes > $length) {
        throw new RuntimeException('CBOR terpotong.');
    }
    $value = 0;
    for ($i = 0; $i < $bytes; $i++) {
        $value = ($value * 256) + ord($data[$offset++]);
    }
    return $value;
}

function webauthn_cbor_decode_item(string $data, int &$offset): mixed
{
    if ($offset >= strlen($data)) {
        throw new RuntimeException('CBOR kosong atau terpotong.');
    }
    $initial = ord($data[$offset++]);
    $major = $initial >> 5;
    $additional = $initial & 0x1f;
    $value = webauthn_read_uint($data, $offset, $additional);

    return match ($major) {
        0 => $value,
        1 => -1 - $value,
        2 => (function () use ($data, &$offset, $value): string {
            if ($offset + $value > strlen($data)) {
                throw new RuntimeException('Byte string CBOR terpotong.');
            }
            $result = substr($data, $offset, $value);
            $offset += $value;
            return $result;
        })(),
        3 => (function () use ($data, &$offset, $value): string {
            if ($offset + $value > strlen($data)) {
                throw new RuntimeException('Text string CBOR terpotong.');
            }
            $result = substr($data, $offset, $value);
            $offset += $value;
            return $result;
        })(),
        4 => (function () use ($data, &$offset, $value): array {
            $result = [];
            for ($i = 0; $i < $value; $i++) {
                $result[] = webauthn_cbor_decode_item($data, $offset);
            }
            return $result;
        })(),
        5 => (function () use ($data, &$offset, $value): array {
            $result = [];
            for ($i = 0; $i < $value; $i++) {
                $key = webauthn_cbor_decode_item($data, $offset);
                $result[$key] = webauthn_cbor_decode_item($data, $offset);
            }
            return $result;
        })(),
        6 => webauthn_cbor_decode_item($data, $offset),
        7 => match ($additional) {
            20 => false,
            21 => true,
            22, 23 => null,
            default => throw new RuntimeException('Simple value CBOR tidak didukung.'),
        },
        default => throw new RuntimeException('Tipe CBOR tidak didukung.'),
    };
}

function webauthn_cbor_decode(string $data): mixed
{
    $offset = 0;
    return webauthn_cbor_decode_item($data, $offset);
}

function webauthn_parse_authenticator_data(string $authData, bool $requireAttestedCredential): array
{
    if (strlen($authData) < 37) {
        throw new RuntimeException('Authenticator data terlalu pendek.');
    }
    $rpIdHash = substr($authData, 0, 32);
    $flags = ord($authData[32]);
    $signCount = unpack('N', substr($authData, 33, 4))[1];
    $result = [
        'rp_id_hash' => $rpIdHash,
        'flags' => $flags,
        'sign_count' => (int)$signCount,
        'user_present' => (bool)($flags & 0x01),
        'user_verified' => (bool)($flags & 0x04),
    ];

    if ($requireAttestedCredential) {
        if (!(bool)($flags & 0x40)) {
            throw new RuntimeException('Credential publik tidak ditemukan pada authenticator data.');
        }
        $offset = 37;
        if (strlen($authData) < $offset + 18) {
            throw new RuntimeException('Attested credential data terpotong.');
        }
        $result['aaguid'] = substr($authData, $offset, 16);
        $offset += 16;
        $credentialLength = unpack('n', substr($authData, $offset, 2))[1];
        $offset += 2;
        if (strlen($authData) < $offset + $credentialLength) {
            throw new RuntimeException('Credential ID terpotong.');
        }
        $result['credential_id'] = substr($authData, $offset, $credentialLength);
        $offset += $credentialLength;
        $cborOffset = $offset;
        $result['credential_public_key'] = webauthn_cbor_decode_item($authData, $cborOffset);
    }

    return $result;
}

function webauthn_cose_ec2_to_pem(array $cose): string
{
    $kty = $cose[1] ?? null;
    $alg = $cose[3] ?? null;
    $crv = $cose[-1] ?? null;
    $x = $cose[-2] ?? null;
    $y = $cose[-3] ?? null;
    if ($kty !== 2 || $alg !== -7 || $crv !== 1 || !is_string($x) || !is_string($y) || strlen($x) !== 32 || strlen($y) !== 32) {
        throw new RuntimeException('Jenis credential passkey belum didukung. Gunakan perangkat dengan algoritma ES256.');
    }
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $x . $y;
    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

function webauthn_verify_client_data(string $clientDataJson, string $expectedType, string $purpose, int $userId, int $deviceId): array
{
    $client = json_decode($clientDataJson, true);
    if (!is_array($client)) {
        throw new RuntimeException('clientDataJSON tidak valid.');
    }
    if (($client['type'] ?? '') !== $expectedType) {
        throw new RuntimeException('Tipe operasi WebAuthn tidak sesuai.');
    }
    if (rtrim((string)($client['origin'] ?? ''), '/') !== webauthn_origin()) {
        throw new RuntimeException('Origin WebAuthn tidak sesuai.');
    }
    if (!empty($client['crossOrigin'])) {
        throw new RuntimeException('Permintaan WebAuthn lintas origin ditolak.');
    }
    $challenge = (string)($client['challenge'] ?? '');
    webauthn_consume_challenge($purpose, $userId, $deviceId, $challenge);
    return $client;
}

function webauthn_assert_rp_and_uv(array $parsed): void
{
    if (!hash_equals(hash('sha256', webauthn_rp_id(), true), (string)$parsed['rp_id_hash'])) {
        throw new RuntimeException('RP ID passkey tidak sesuai.');
    }
    if (empty($parsed['user_present']) || empty($parsed['user_verified'])) {
        throw new RuntimeException('Verifikasi biometrik atau kunci layar tidak selesai.');
    }
}

function webauthn_create_proof(int $userId, int $deviceId): string
{
    $token = webauthn_b64url_encode(random_bytes(32));
    $_SESSION['biometric_proof'] = [
        'token_hash' => hash('sha256', $token),
        'user_id' => $userId,
        'device_id' => $deviceId,
        'expires_at' => time() + 90,
        'used' => false,
    ];
    return $token;
}

function webauthn_consume_proof(string $token, int $userId, int $deviceId): void
{
    $proof = $_SESSION['biometric_proof'] ?? null;
    if (!is_array($proof)
        || !empty($proof['used'])
        || (int)($proof['user_id'] ?? 0) !== $userId
        || (int)($proof['device_id'] ?? 0) !== $deviceId
        || (int)($proof['expires_at'] ?? 0) < time()
        || !hash_equals((string)($proof['token_hash'] ?? ''), hash('sha256', $token))
    ) {
        throw new RuntimeException('Bukti biometrik tidak valid atau sudah kedaluwarsa. Silakan verifikasi ulang.');
    }
    $_SESSION['biometric_proof']['used'] = true;
}
