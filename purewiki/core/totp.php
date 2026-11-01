<?php
/**
 * PureWiki - Two-Factor Authentication
 *
 * Time-based onetime codes (TOTP) for the dashboard login
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

const PW_TOTP_PERIOD = 30;
const PW_TOTP_DIGITS = 6;
const PW_BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/*
 * How a code is built
 *
 *   secret  "GEZD GNBV ..."  --base32Decode-->  key (20 bytes)
 *   time() / 30              --pack('J')---->   counter (8 bytes)
 *
 *   HMAC-SHA1(key, counter) = 20 bytes
 *   +----+----+----+----+----+-- ... --+----+
 *   | 1f | 86 | 98 | 69 | 0e |   ...   | 5a |   last byte & 0x0F = offset (here 10)
 *   +----+----+----+----+----+-- ... --+----+
 *                  4 bytes from offset, highest bit removed
 *                  --> 1357872921 % 1000000 = "872921"
 */

/** Returns a new random Base32 secret with 160 bits. */
function generateTotpSecret(): string {
    return base32Encode(random_bytes(20));
}

/** Returns the Base32 encoding of binary data without padding. */
function base32Encode(string $data): string {
    $bits = '';
    foreach (str_split($data) as $char) {
        $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $result = '';
    foreach (str_split($bits, 5) as $chunk) {
        $result .= PW_BASE32_CHARS[bindec(str_pad($chunk, 5, '0'))];
    }
    return $result;
}

/** Returns the binary data of a Base32 string, or null if it contains invalid characters. */
function base32Decode(string $secret): ?string {
    $secret = strtoupper(str_replace([' ', '='], '', $secret));
    $bits = '';
    foreach (str_split($secret) as $char) {
        $pos = strpos(PW_BASE32_CHARS, $char);
        if ($pos === false) return null;
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $result = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) $result .= chr(bindec($byte));
    }
    return $result;
}

/** Returns the 6-digit code of a secret for a time step. */
function getTotpCode(string $secret, int $timeStep): string {
    $key = base32Decode($secret) ?? '';
    $hash = hash_hmac('sha1', pack('J', $timeStep), $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
    return str_pad((string) ($value % (10 ** PW_TOTP_DIGITS)), PW_TOTP_DIGITS, '0', STR_PAD_LEFT);
}

/**
 * Checks a code against the secret and allows one time step of clock drift.
 * Returns the matching time step or null if code is wrong or was already used.
 *
 * @param int $lastStep Time step of the last accepted code so code only works once
 */
function verifyTotpCode(string $secret, string $code, int $lastStep = 0): ?int {
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^\d{' . PW_TOTP_DIGITS . '}$/', $code)) return null;

    $current = intdiv(time(), PW_TOTP_PERIOD);
    foreach ([$current - 1, $current, $current + 1] as $step) {
        if ($step > $lastStep && hash_equals(getTotpCode($secret, $step), $code)) {
            return $step;
        }
    }
    return null;
}

/** Returns the otpauth:// link that authenticator apps read from QR code. */
function getTotpUri(string $secret, string $username, string $issuer): string {
    $label = rawurlencode($issuer) . ':' . rawurlencode($username);
    return 'otpauth://totp/' . $label . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer)
        . '&digits=' . PW_TOTP_DIGITS . '&period=' . PW_TOTP_PERIOD;
}
