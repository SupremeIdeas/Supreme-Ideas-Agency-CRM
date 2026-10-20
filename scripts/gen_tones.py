#!/usr/bin/env python3
"""Generates the built-in notification / ringtone library (original synthesis, no third-party samples, so no licence to track).
Writes 16-bit mono 22.05 kHz WAV files into database/seeders/sounds/. Re-run to regenerate; the seeder copies them to the media disk.
"""
import math, struct, wave, os, random
SR = 22050
OUT = os.path.join(os.path.dirname(__file__), '..', 'database', 'seeders', 'sounds')
os.makedirs(OUT, exist_ok=True)
random.seed(7)

def tone(freq, dur, vol=0.5, decay=6.0, partials=((1, 1.0),), attack=0.004, vib=0.0):
    n = int(SR * dur); out = [0.0] * n
    for i in range(n):
        t = i / SR
        env = min(1.0, t / attack) * math.exp(-decay * t)
        f = freq * (1 + vib * math.sin(2 * math.pi * 5.5 * t))
        s = sum(a * math.sin(2 * math.pi * f * m * t) for m, a in partials)
        out[i] = s * env * vol
    return out

def mix(*parts):
    n = max(int(off * SR) + len(p) for off, p in parts); out = [0.0] * n
    for off, p in parts:
        o = int(off * SR)
        for i, v in enumerate(p):
            if o + i < n: out[o + i] += v
    return out

def at(t, samples): return (t, samples)

def glide(f0, f1, dur, vol=0.45, decay=5.0):
    n = int(SR * dur); out = [0.0] * n; ph = 0.0
    for i in range(n):
        t = i / SR; f = f0 + (f1 - f0) * (t / dur)
        ph += 2 * math.pi * f / SR
        out[i] = math.sin(ph) * min(1.0, t / 0.003) * math.exp(-decay * t) * vol
    return out

def finish(name, samples, peak=0.85, fade=0.02):
    m = max(1e-9, max(abs(s) for s in samples)); g = peak / m
    n = len(samples); f = int(SR * fade)
    path = os.path.join(OUT, name + '.wav')
    with wave.open(path, 'wb') as w:
        w.setnchannels(1); w.setsampwidth(2); w.setframerate(SR)
        frames = bytearray()
        for i, s in enumerate(samples):
            v = s * g
            if i > n - f: v *= (n - i) / f
            frames += struct.pack('<h', int(max(-1, min(1, v)) * 32767))
        w.writeframes(bytes(frames))

BELL = ((1, 1.0), (2.76, 0.45), (5.4, 0.22), (8.9, 0.1))
MARIMBA = ((1, 1.0), (4.0, 0.35), (10.0, 0.08))
SOFT = ((1, 1.0), (2, 0.25), (3, 0.08))
N = {'C5': 523.25, 'D5': 587.33, 'E5': 659.25, 'G5': 783.99, 'A5': 880.0, 'C6': 1046.5, 'E6': 1318.5, 'G6': 1568.0, 'A4': 440.0, 'E5b': 622.25, 'B5': 987.77, 'C7': 2093.0, 'F5': 698.46, 'G4': 392.0}

# credit_alert
finish('credit-cash-chime', mix(at(0, tone(N['C6'], 0.9, .5, 5, BELL)), at(0.13, tone(N['E6'], 1.0, .5, 5, BELL)), at(0.26, tone(N['G6'], 1.1, .4, 4.5, BELL))))
finish('credit-coin-drop', mix(at(0, tone(N['C7'], 0.5, .45, 9, BELL)), at(0.07, tone(2637.0, 0.8, .35, 6, BELL)), at(0.12, tone(3136.0, 0.9, .22, 5, BELL))))
finish('credit-bright-bell', mix(at(0, tone(N['A5'], 1.4, .55, 3.2, BELL))))
finish('credit-vault-arp', mix(*[at(i * 0.09, tone(f, 0.8, .42, 6, MARIMBA)) for i, f in enumerate([N['C5'], N['E5'], N['G5'], N['C6']])]))
# message / general
finish('msg-soft-pop', mix(at(0, glide(900, 1400, 0.12, .5, 14)), at(0.1, tone(N['A5'], 0.25, .3, 14, SOFT))))
finish('msg-droplet', glide(1700, 700, 0.28, .55, 10))
finish('msg-two-tap', mix(at(0, tone(N['E5'], 0.35, .55, 11, MARIMBA)), at(0.16, tone(N['G5'], 0.4, .55, 11, MARIMBA))))
# message / naara (member-to-member): warm rising pair
finish('msg-naara-hello', mix(at(0, tone(N['G5'], 0.45, .5, 7, SOFT, vib=.004)), at(0.15, tone(N['C6'], 0.6, .5, 6, SOFT, vib=.004)), at(0.15, tone(N['E6'], 0.6, .2, 7, SOFT))))
finish('msg-naara-glow', mix(at(0, tone(N['C5'], 0.5, .45, 6, SOFT)), at(0.12, tone(N['E5'], 0.5, .45, 6, SOFT)), at(0.24, tone(N['A5'], 0.7, .45, 5, SOFT))))
# message / naaraline (a number outside Naara): crisp triple tick, clearly different
finish('msg-line-ticks', mix(*[at(i * 0.11, tone(1800, 0.09, .55, 30, ((1, 1.0), (2, .3)))) for i in range(3)]))
finish('msg-line-sms', mix(at(0, tone(1200, 0.12, .5, 22, ((1, 1.0), (2, .4)))), at(0.15, tone(900, 0.25, .5, 12, ((1, 1.0), (2, .3))))))
# push
finish('push-gentle-alert', mix(at(0, tone(N['E5'], 1.0, .5, 4.5, BELL))))
finish('push-beacon', mix(at(0, tone(N['A4'], 0.3, .5, 9, SOFT)), at(0.32, tone(N['A5'], 0.7, .5, 6, SOFT))))
finish('push-nudge', mix(at(0, tone(N['G5'], 0.2, .5, 16, MARIMBA)), at(0.12, tone(N['G5'], 0.3, .5, 14, MARIMBA))))
# reward
finish('reward-level-up', mix(*[at(i * 0.075, tone(f, 0.7, .45, 6, BELL)) for i, f in enumerate([N['C5'], N['E5'], N['G5'], N['C6'], N['E6']])]))
finish('reward-sparkle', mix(*[at(i * 0.05, tone(f, 0.5, .3, 9, BELL)) for i, f in enumerate([2093.0, 2637.0, 3136.0, 2349.0, 3520.0, 4186.0])]))
finish('reward-mini-fanfare', mix(at(0, tone(N['G5'], 0.18, .5, 4, SOFT)), at(0.17, tone(N['G5'], 0.18, .5, 4, SOFT)), at(0.34, tone(N['G5'], 0.18, .5, 4, SOFT)), at(0.52, tone(N['C6'], 0.9, .55, 3.5, ((1, 1), (2, .5), (3, .25))))))

# ringtones (about 2.4 s, loop cleanly: they end in silence)
def ring_classic():
    parts = []
    for burst in (0.0, 0.5):
        for k in range(8):
            t0 = burst + k * 0.0625
            parts.append(at(t0, tone(440, 0.05, .35, 0, ((1, 1.0),), attack=.002)))
            parts.append(at(t0, tone(480, 0.05, .35, 0, ((1, 1.0),), attack=.002)))
    return mix(*parts, at(2.4, [0.0]))
def ring_marimba():
    mel = [(N['E5'], 0.0), (N['G5'], 0.18), (N['A5'], 0.36), (N['G5'], 0.54), (N['E5'], 0.9), (N['G5'], 1.08), (N['C6'], 1.26), (N['A5'], 1.44)]
    return mix(*[at(t, tone(f, 0.6, .5, 7, MARIMBA)) for f, t in mel], at(2.4, [0.0]))
def ring_pulse():
    parts = []
    for k in range(6):
        parts.append(at(k * 0.36, tone(660 if k % 2 == 0 else 880, 0.22, .45, 9, ((1, 1.0), (2, .5), (3, .3)))))
    return mix(*parts, at(2.4, [0.0]))
def ring_chime():
    return mix(at(0, tone(N['C6'], 1.2, .5, 3.2, BELL)), at(0.6, tone(N['G5'], 1.2, .5, 3.2, BELL)), at(1.2, tone(N['E6'], 1.2, .45, 3.0, BELL)), at(2.4, [0.0]))
finish('ring-classic', ring_classic()); finish('ring-marimba-loop', ring_marimba()); finish('ring-digital-pulse', ring_pulse()); finish('ring-soft-chime', ring_chime())
print(sorted(os.listdir(OUT)))
