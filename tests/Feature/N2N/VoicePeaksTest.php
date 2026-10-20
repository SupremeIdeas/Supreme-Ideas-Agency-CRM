<?php

namespace Tests\Feature\N2N;

use App\Services\N2N\Media\MediaService;
use Tests\TestCase;

/** The waveform that travels with a voice note is untrusted input: only a short list of 0..100 integers survives (N2N, MASTER-ONLY). */
class VoicePeaksTest extends TestCase
{
    public function test_a_clean_waveform_is_kept_clamped_and_capped(): void
    {
        $in = array_merge([5, 50, 100, 250, -9, 33.6], range(10, 30), array_fill(0, 120, 70));
        $out = MediaService::cleanPeaks($in);
        $this->assertCount(96, $out);
        $this->assertSame([5, 50, 100, 100, 0, 34], array_slice($out, 0, 6));
        $this->assertSame([], array_filter($out, fn ($v) => ! is_int($v) || $v < 0 || $v > 100));
    }

    public function test_anything_malformed_is_dropped_not_stored(): void
    {
        $this->assertNull(MediaService::cleanPeaks(null));
        $this->assertNull(MediaService::cleanPeaks('1,2,3'));
        $this->assertNull(MediaService::cleanPeaks([]));
        $this->assertNull(MediaService::cleanPeaks([1, 2, 3]), 'too short to draw');
        $this->assertNull(MediaService::cleanPeaks([10, 20, 30, 40, 50, 60, 70, '<script>']));
        $this->assertNull(MediaService::cleanPeaks(['a' => ['b']]));
    }
}
