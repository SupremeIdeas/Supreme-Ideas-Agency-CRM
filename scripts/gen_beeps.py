#!/usr/bin/env python3
"""Original synthesis of the built-in beep tones (category "beep": a message sent, something that failed). 44.1 kHz mono 16-bit WAV, a few hundred ms each.
    python3 scripts/gen_beeps.py   # writes database/seeders/sounds/beep-*.wav
"""
import math, struct, wave, os
RATE = 44100
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'database', 'seeders', 'sounds')

def tone(freq, ms, shape='sine', f2=None, vol=0.55, attack=4, release=40):
    n = int(RATE * ms / 1000); out = []
    for i in range(n):
        t = i / RATE; f = freq if f2 is None else freq + (f2 - freq) * i / n
        ph = 2 * math.pi * f * t
        s = math.sin(ph) if shape == 'sine' else (2 / math.pi * math.asin(math.sin(ph)) if shape == 'tri' else (1 if math.sin(ph) >= 0 else -1) * 0.6 + 0.4 * math.sin(ph))
        a = min(1, i / (RATE * attack / 1000)); r = min(1, (n - i) / (RATE * release / 1000))
        out.append(s * vol * a * r)
    return out

def gap(ms): return [0.0] * int(RATE * ms / 1000)

def save(name, parts):
    s = [x for p in parts for x in p]
    with wave.open(os.path.join(OUT, name + '.wav'), 'wb') as w:
        w.setnchannels(1); w.setsampwidth(2); w.setframerate(RATE)
        w.writeframes(b''.join(struct.pack('<h', int(max(-1, min(1, x)) * 32767)) for x in s))
    print(name, round(len(s) / RATE * 1000), 'ms')

# sent
save('beep-send-lift', [tone(880, 70, 'sine'), tone(1320, 110, 'sine', release=70)])
save('beep-send-tick', [tone(1100, 55, 'tri', release=45)])
save('beep-send-double', [tone(1500, 40, 'tri', release=30), gap(45), tone(1500, 40, 'tri', release=30)])
# error
save('beep-error-buzz', [tone(220, 130, 'square', vol=0.45), gap(60), tone(220, 130, 'square', vol=0.45)])
save('beep-error-fall', [tone(880, 200, 'sine', f2=440, release=120)])
save('beep-error-triple', [tone(330, 80, 'tri'), gap(50), tone(330, 80, 'tri'), gap(50), tone(330, 80, 'tri')])
