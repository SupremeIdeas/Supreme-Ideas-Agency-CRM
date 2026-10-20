<?php

namespace App\Services\WhiteLabel\Payments;

/** Original Keccak-256 (Ethereum's hash, padding 0x01 — NOT NIST SHA3-256). PHP core has no keccak, so it lives here. */
final class Keccak256
{
    private const RC = [
        '0000000000000001', '0000000000008082', '800000000000808a', '8000000080008000', '000000000000808b', '0000000080000001',
        '8000000080008081', '8000000000008009', '000000000000008a', '0000000000000088', '0000000080008009', '000000008000000a',
        '000000008000808b', '800000000000008b', '8000000000008089', '8000000000008003', '8000000000008002', '8000000000000080',
        '000000000000800a', '800000008000000a', '8000000080008081', '8000000000008080', '0000000080000001', '8000000080008008',
    ];

    private const ROT = [[0, 36, 3, 41, 18], [1, 44, 10, 45, 2], [62, 6, 43, 15, 61], [28, 55, 25, 21, 56], [27, 20, 39, 8, 14]];

    public static function hex(string $data): string
    {
        $rate = 136;
        $data .= "\x01";
        $data = str_pad($data, (int) (ceil(strlen($data) / $rate) * $rate), "\0");
        $data[strlen($data) - 1] = chr(ord($data[strlen($data) - 1]) | 0x80);

        $s = array_fill(0, 25, 0);
        foreach (str_split($data, $rate) as $block) {
            for ($i = 0; $i < 17; $i++) {
                $s[$i] ^= unpack('P', substr($block, $i * 8, 8))[1];
            }
            $s = self::permute($s);
        }
        $out = '';
        for ($i = 0; $i < 4; $i++) {
            $out .= pack('P', $s[$i]);
        }

        return bin2hex($out);
    }

    private static function rotl(int $x, int $n): int
    {
        if ($n === 0) {
            return $x;
        }

        return ($x << $n) | (($x >> (64 - $n)) & ((1 << $n) - 1));
    }

    /** @param  list<int>  $a */
    private static function permute(array $a): array
    {
        foreach (self::RC as $rc) {
            $c = [];
            for ($x = 0; $x < 5; $x++) {
                $c[$x] = $a[$x] ^ $a[$x + 5] ^ $a[$x + 10] ^ $a[$x + 15] ^ $a[$x + 20];
            }
            for ($x = 0; $x < 5; $x++) {
                $d = $c[($x + 4) % 5] ^ self::rotl($c[($x + 1) % 5], 1);
                for ($y = 0; $y < 5; $y++) {
                    $a[$x + 5 * $y] ^= $d;
                }
            }
            $b = [];
            for ($x = 0; $x < 5; $x++) {
                for ($y = 0; $y < 5; $y++) {
                    $b[$y + 5 * ((2 * $x + 3 * $y) % 5)] = self::rotl($a[$x + 5 * $y], self::ROT[$x][$y]);
                }
            }
            for ($x = 0; $x < 5; $x++) {
                for ($y = 0; $y < 5; $y++) {
                    $a[$x + 5 * $y] = $b[$x + 5 * $y] ^ ((~$b[(($x + 1) % 5) + 5 * $y]) & $b[(($x + 2) % 5) + 5 * $y]);
                }
            }
            $a[0] ^= unpack('J', hex2bin($rc))[1];
        }

        return $a;
    }
}
