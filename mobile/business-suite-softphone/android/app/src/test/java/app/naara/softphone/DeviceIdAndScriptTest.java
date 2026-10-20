package app.naara.softphone;

import static org.junit.Assert.*;

import org.junit.Test;

public class DeviceIdAndScriptTest {
    @Test public void deviceIdIsStableAndMatchesTheServerRule() {
        MemoryStore s = new MemoryStore();
        String a = DeviceId.get(s);
        assertEquals(a, DeviceId.get(s));
        assertTrue(a.matches("^[A-Za-z0-9._-]{8,64}$"));
    }

    @Test public void aCorruptStoredIdIsReplaced() {
        MemoryStore s = new MemoryStore();
        s.put("device_id", "bad id!");
        assertTrue(DeviceId.isValid(DeviceId.get(s)));
    }

    @Test public void pairScriptTalksToTheRealPairRouteWithCsrfAndOnlyHandsTheTokenOver() {
        String js = PairScript.build();
        assertTrue(js.contains("/softphone/native/pair"));
        assertTrue(js.contains("X-CSRF-TOKEN"));
        assertTrue(js.contains("credentials:'same-origin'"));
        assertTrue(js.contains("NaaraNative.savePairingToken"));
        assertTrue(js.contains("NaaraNative.unpair"));
        assertTrue(js.contains("logout"));
        assertFalse("the token must never be written to web storage", js.contains("localStorage") || js.contains("sessionStorage") || js.contains("document.cookie"));
    }

    /** Lets the real-browser check (tests/mobile) run the exact script the app injects: NAARA_DUMP_PAIRSCRIPT=/path/to/out.js */
    @Test public void dumpsTheScriptForTheBrowserCheckWhenAsked() throws Exception {
        String out = System.getenv("NAARA_DUMP_PAIRSCRIPT");
        if (out != null && !out.isEmpty()) {
            java.nio.file.Files.write(java.nio.file.Paths.get(out), PairScript.build().getBytes(java.nio.charset.StandardCharsets.UTF_8));
        }
    }
}
