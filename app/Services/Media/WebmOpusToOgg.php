<?php

namespace App\Services\Media;

/**
 * Re-wraps an Opus voice note recorded by a browser (WebM container) as Ogg Opus, in plain PHP with no ffmpeg and no extension. The audio is not decoded or
 * re-encoded: the Opus packets are copied across, so it is lossless and takes milliseconds, even on the most locked-down shared host.
 *
 * Why it matters: Chrome, Edge and Android browsers record voice as WebM/Opus; WhatsApp and Telegram accept voice notes only as Ogg/Opus (or AAC/MP3). This turns
 * "voice notes need ffmpeg" into "voice notes just work" for those browsers. A browser that records AAC (Safari) needs no conversion at all.
 *
 * Handles MediaRecorder's live-stream WebM (segments and clusters of unknown length) by reading the file as a flat run of elements.
 */
class WebmOpusToOgg
{
    private const MASTER = [0x18538067 => 'segment', 0x1F43B675 => 'cluster', 0x1654AE6B => 'tracks', 0xAE => 'entry', 0xA0 => 'group', 0xE1 => 'audio'];

    /** @return ?string Ogg bytes, or null when the file is not Opus-in-WebM (the caller falls back). */
    public static function convert(string $webm): ?string
    {
        if (strlen($webm) < 64 || substr($webm, 0, 4) !== "\x1A\x45\xDF\xA3") {
            return null;
        }
        $n = strlen($webm);
        $i = 0;
        $tracks = [];       // number => ['codec' => ..., 'private' => ..., 'channels' => ..., 'rate' => ...]
        $cur = null;
        $packets = [];
        while ($i < $n) {
            $id = self::readId($webm, $i);
            if ($id === null) {
                break;
            }
            $size = self::readSize($webm, $i);
            if ($size === null) {
                break;
            }
            if (isset(self::MASTER[$id])) {
                if ($id === 0xAE) {
                    $cur = count($tracks) + 1000;   // placeholder key until the track number is read
                    $tracks[$cur] = ['codec' => null, 'private' => '', 'channels' => 1, 'rate' => 48000, 'number' => null];
                }

                continue;   // children follow immediately; sizes (possibly unknown) are not needed
            }
            if ($size < 0) {   // unknown size on a non-master element: cannot continue
                break;
            }
            $data = substr($webm, $i, $size);
            $i += $size;
            switch ($id) {
                case 0xD7: $cur !== null && $tracks[$cur]['number'] = self::uint($data);
                    break;
                case 0x86: $cur !== null && $tracks[$cur]['codec'] = $data;
                    break;
                case 0x63A2: $cur !== null && $tracks[$cur]['private'] = $data;
                    break;
                case 0x9F: $cur !== null && $tracks[$cur]['channels'] = max(1, self::uint($data));
                    break;
                case 0xB5: $cur !== null && $tracks[$cur]['rate'] = (int) self::float($data);
                    break;
                case 0xA3:   // SimpleBlock
                case 0xA1:   // Block
                    $p = self::blockPacket($data);
                    if ($p !== null) {
                        $packets[$p[0]][] = $p[1];
                    }
                    break;
            }
        }
        $audio = null;
        foreach ($tracks as $t) {
            if (($t['codec'] ?? null) === 'A_OPUS') {
                $audio = $t;
                break;
            }
        }
        if (! $audio || $audio['number'] === null || empty($packets[$audio['number']])) {
            return null;
        }

        return self::ogg($packets[$audio['number']], $audio['channels'], $audio['private']);
    }

    private static function readId(string $b, int &$i): ?int
    {
        if ($i >= strlen($b)) {
            return null;
        }
        $first = ord($b[$i]);
        $len = $first >= 0x80 ? 1 : ($first >= 0x40 ? 2 : ($first >= 0x20 ? 3 : ($first >= 0x10 ? 4 : 0)));
        if ($len === 0 || $i + $len > strlen($b)) {
            return null;
        }
        $v = 0;
        for ($k = 0; $k < $len; $k++) {
            $v = ($v << 8) | ord($b[$i + $k]);
        }
        $i += $len;

        return $v;
    }

    /** -1 = unknown size. */
    private static function readSize(string $b, int &$i): ?int
    {
        if ($i >= strlen($b)) {
            return null;
        }
        $first = ord($b[$i]);
        $len = 1;
        $mask = 0x80;
        while ($len <= 8 && ! ($first & $mask)) {
            $len++;
            $mask >>= 1;
        }
        if ($len > 8 || $i + $len > strlen($b)) {
            return null;
        }
        $v = $first & ($mask - 1);
        $allOnes = $v === ($mask - 1);
        for ($k = 1; $k < $len; $k++) {
            $byte = ord($b[$i + $k]);
            $v = ($v << 8) | $byte;
            $allOnes = $allOnes && $byte === 0xFF;
        }
        $i += $len;

        return $allOnes ? -1 : $v;
    }

    private static function uint(string $d): int
    {
        $v = 0;
        for ($k = 0; $k < strlen($d); $k++) {
            $v = ($v << 8) | ord($d[$k]);
        }

        return $v;
    }

    private static function float(string $d): float
    {
        return strlen($d) === 8 ? unpack('E', $d)[1] : (strlen($d) === 4 ? unpack('G', $d)[1] : 0.0);
    }

    /** @return ?array{0: int, 1: string} track number, Opus packet */
    private static function blockPacket(string $d): ?array
    {
        if (strlen($d) < 5) {
            return null;
        }
        $first = ord($d[0]);
        $len = $first >= 0x80 ? 1 : ($first >= 0x40 ? 2 : 3);
        $track = $first & (0xFF >> $len);
        for ($k = 1; $k < $len; $k++) {
            $track = ($track << 8) | ord($d[$k]);
        }
        $flags = ord($d[$len + 2]);
        if (($flags & 0x06) !== 0) {
            return null;   // laced blocks do not occur in Opus recordings; refuse rather than mis-copy
        }

        return [$track, substr($d, $len + 3)];
    }

    /** Samples (at 48 kHz) in one Opus packet, from its table-of-contents byte. */
    private static function packetSamples(string $p): int
    {
        if ($p === '') {
            return 0;
        }
        $toc = ord($p[0]);
        $cfg = $toc >> 3;
        $code = $toc & 3;
        if ($cfg < 12) {
            $ms = [10, 20, 40, 60][$cfg & 3];
        } elseif ($cfg < 16) {
            $ms = [10, 20][$cfg & 1];
        } else {
            $ms = [2.5, 5, 10, 20][$cfg & 3];
        }
        $frames = $code === 0 ? 1 : ($code === 3 ? (strlen($p) > 1 ? (ord($p[1]) & 0x3F) : 1) : 2);

        return (int) round($ms * 48 * max(1, $frames));
    }

    /** @param list<string> $packets */
    private static function ogg(array $packets, int $channels, string $private): string
    {
        $preSkip = (strlen($private) >= 12 && str_starts_with($private, 'OpusHead')) ? unpack('v', substr($private, 10, 2))[1] : 312;
        $rate = (strlen($private) >= 16 && str_starts_with($private, 'OpusHead')) ? unpack('V', substr($private, 12, 4))[1] : 48000;
        $head = 'OpusHead'.chr(1).chr($channels).pack('v', $preSkip).pack('V', $rate).pack('v', 0).chr(0);
        $vendor = 'Naara';
        $tags = 'OpusTags'.pack('V', strlen($vendor)).$vendor.pack('V', 0);
        $serial = random_int(1, 0x7FFFFFFF);
        $seq = 0;
        $out = self::page([$head], 0x02, 0, $serial, $seq++);
        $out .= self::page([$tags], 0x00, 0, $serial, $seq++);
        $granule = $preSkip;
        $batch = [];
        $batchGranule = $granule;
        $segs = 0;
        $total = count($packets);
        foreach ($packets as $idx => $p) {
            $granule += self::packetSamples($p);
            $need = intdiv(strlen($p), 255) + 1;
            if ($batch && ($segs + $need > 250)) {
                $out .= self::page($batch, 0x00, $batchGranule, $serial, $seq++);
                $batch = [];
                $segs = 0;
            }
            $batch[] = $p;
            $segs += $need;
            $batchGranule = $granule;
            if ($idx === $total - 1) {
                $out .= self::page($batch, 0x04, $batchGranule, $serial, $seq++);
                $batch = [];
            }
        }

        return $out;
    }

    /** @param list<string> $packets one page; every packet ends inside it */
    private static function page(array $packets, int $type, int $granule, int $serial, int $seq): string
    {
        $table = '';
        $body = '';
        foreach ($packets as $p) {
            $l = strlen($p);
            $table .= str_repeat("\xFF", intdiv($l, 255)).chr($l % 255);
            $body .= $p;
        }
        $hdr = 'OggS'.chr(0).chr($type).pack('P', $granule).pack('V', $serial).pack('V', $seq).pack('V', 0).chr(strlen($table)).$table;
        $crc = self::crc($hdr.$body);

        return substr($hdr, 0, 22).pack('V', $crc).substr($hdr, 26).$body;
    }

    private static function crc(string $s): int
    {
        static $t = null;
        if ($t === null) {
            $t = [];
            for ($i = 0; $i < 256; $i++) {
                $r = $i << 24;
                for ($k = 0; $k < 8; $k++) {
                    $r = ($r & 0x80000000) ? ((($r << 1) ^ 0x04C11DB7) & 0xFFFFFFFF) : (($r << 1) & 0xFFFFFFFF);
                }
                $t[$i] = $r;
            }
        }
        $crc = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $crc = (($crc << 8) & 0xFFFFFFFF) ^ $t[(($crc >> 24) & 0xFF) ^ ord($s[$i])];
        }

        return $crc;
    }
}
