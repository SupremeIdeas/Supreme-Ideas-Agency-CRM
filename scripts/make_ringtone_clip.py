#!/usr/bin/env python3
"""Cut a full-length track into a ringtone clip: first N seconds, short fade in, gentle fade out, peak-normalised, 128 kbps stereo MP3.

    pip install miniaudio lameenc numpy
    python3 scripts/make_ringtone_clip.py input.mp3 database/seeders/sounds/ring-name.mp3 [seconds=30]

A ringtone is decoded in the browser (Web Audio) every time a call rings, so a 30 second clip (about 480 KB) starts in a fraction of a second where a
full 4-6 minute song (3 MB or more) would not. Used to prepare the Zayon Whyte ringtones; works for any track an admin wants to ship as a built-in.
"""
import sys
import miniaudio
import lameenc
import numpy as np

src, dst = sys.argv[1], sys.argv[2]
secs = float(sys.argv[3]) if len(sys.argv) > 3 else 30.0
RATE = 44100
dec = miniaudio.decode_file(src, output_format=miniaudio.SampleFormat.SIGNED16, nchannels=2, sample_rate=RATE)
pcm = np.frombuffer(dec.samples, dtype=np.int16).reshape(-1, 2).astype(np.float32)
pcm = pcm[: int(secs * RATE)]
n = len(pcm)
fi = int(0.04 * RATE); fo = int(1.6 * RATE)
env = np.ones(n, dtype=np.float32)
env[:fi] = np.linspace(0, 1, fi)
env[-fo:] = np.cos(np.linspace(0, np.pi / 2, fo)) ** 2      # smooth equal-power fade out
pcm *= env[:, None]
peak = np.abs(pcm).max() or 1.0
pcm *= (0.891 * 32767) / peak                                # -1 dBFS
out = pcm.astype(np.int16)
enc = lameenc.Encoder()
enc.set_bit_rate(128); enc.set_in_sample_rate(RATE); enc.set_channels(2); enc.set_quality(2)
data = enc.encode(out.tobytes()) + enc.flush()
open(dst, 'wb').write(data)
print(dst, len(data) // 1024, 'KB', round(n / RATE, 1), 's')
