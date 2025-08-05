<?php
// helpers/encryption.php
require_once __DIR__ . '/../config/smtp_key.php';

function smtp_encrypt(string $plaintext): string {
    $key    = hex2bin(SMTP_ENC_KEY);
    $cipher = 'AES-256-CBC';
    $ivlen  = openssl_cipher_iv_length($cipher);
    $iv     = random_bytes($ivlen);
    $ct     = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $ct);
}

function smtp_decrypt(string $b64): string {
    $key    = hex2bin(SMTP_ENC_KEY);
    $cipher = 'AES-256-CBC';
    $data   = base64_decode($b64);
    $ivlen  = openssl_cipher_iv_length($cipher);
    $iv     = substr($data, 0, $ivlen);
    $ct     = substr($data, $ivlen);
    return openssl_decrypt($ct, $cipher, $key, OPENSSL_RAW_DATA, $iv);
}
