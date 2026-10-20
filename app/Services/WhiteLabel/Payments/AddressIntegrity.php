<?php

namespace App\Services\WhiteLabel\Payments;

/**
 * Crypto receiving-address integrity (Funnel blueprint 0.2 / 5.6): the check an admin edit must pass before an address
 * can be saved, and the same check CI runs over every stored address.
 *   TRC20  base58check, 21-byte payload starting 0x41
 *   BEP20 / ERC20  0x + 40 hex; EIP-55 mixed-case checksum when the address is mixed-case
 *   Solana  base58 decoding to exactly 32 bytes
 */
final class AddressIntegrity
{
    private const B58 = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /** @return array{ok: bool, reason: ?string} */
    public static function check(string $network, string $address): array
    {
        $address = trim($address);
        $ok = match (strtolower($network)) {
            'trc20', 'tron' => self::tron($address),
            'bep20', 'erc20', 'bsc', 'ethereum', 'evm' => self::evm($address),
            'solana', 'sol' => self::solana($address),
            default => null,
        };
        if ($ok === null) {
            return ['ok' => false, 'reason' => "Unknown network '{$network}'."];
        }

        return $ok ? ['ok' => true, 'reason' => null] : ['ok' => false, 'reason' => 'That address has the wrong format or a bad checksum for '.$network.'. Re-copy it from the wallet.'];
    }

    public static function tron(string $a): bool
    {
        $raw = self::b58decode($a);

        return $raw !== null && strlen($raw) === 25 && $raw[0] === "\x41"
            && substr(hash('sha256', hash('sha256', substr($raw, 0, 21), true), true), 0, 4) === substr($raw, 21);
    }

    public static function solana(string $a): bool
    {
        $raw = self::b58decode($a);

        return $raw !== null && strlen($raw) === 32;
    }

    public static function evm(string $a): bool
    {
        if (! preg_match('/^0x[0-9a-fA-F]{40}$/', $a)) {
            return false;
        }
        $hex = substr($a, 2);
        if ($hex === strtolower($hex) || $hex === strtoupper($hex)) {
            return true; // not checksummed: shape only (lower/upper case carries no checksum)
        }
        $hash = Keccak256::hex(strtolower($hex));
        for ($i = 0; $i < 40; $i++) {
            $c = $hex[$i];
            if (ctype_alpha($c) && ((hexdec($hash[$i]) >= 8) !== ctype_upper($c))) {
                return false;
            }
        }

        return true;
    }

    /** Base58 to bytes without bcmath/gmp (not guaranteed on shared hosting). */
    private static function b58decode(string $s): ?string
    {
        if ($s === '' || strspn($s, self::B58) !== strlen($s)) {
            return null;
        }
        $bytes = [0]; // big-endian base-256 digits, grown as needed
        foreach (str_split($s) as $c) {
            $carry = strpos(self::B58, $c);
            for ($i = count($bytes) - 1; $i >= 0; $i--) {
                $carry += $bytes[$i] * 58;
                $bytes[$i] = $carry & 0xFF;
                $carry >>= 8;
            }
            while ($carry > 0) {
                array_unshift($bytes, $carry & 0xFF);
                $carry >>= 8;
            }
        }
        $raw = implode('', array_map('chr', $bytes));
        $raw = ltrim($raw, "\0"); // the arithmetic above leaves a leading zero byte; real leading zeros come from the '1's
        $leading = strlen($s) - strlen(ltrim($s, '1'));

        return str_repeat("\0", $leading).$raw;
    }
}
