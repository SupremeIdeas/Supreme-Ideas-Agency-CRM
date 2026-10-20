package app.naara.softphone;

import static org.junit.Assert.*;

import org.json.JSONObject;
import org.junit.After;
import org.junit.Before;
import org.junit.Test;

import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.net.ServerSocket;
import java.net.Socket;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.atomic.AtomicReference;

/** The phone's HTTP client against a real local HTTP server: headers, body, and what each status code makes the app do. */
public class SoftphoneApiTest {
        private String base;
    private final AtomicReference<String> auth = new AtomicReference<>();
    private final AtomicReference<String> path = new AtomicReference<>();
    private final AtomicReference<JSONObject> body = new AtomicReference<>();
    private volatile int status = 200;
    private volatile String reply = "{}";

    private ServerSocket socket;
    private Thread loop;

    @Before
    public void start() throws Exception {
        socket = new ServerSocket(0, 5, java.net.InetAddress.getByName("127.0.0.1"));
        base = "http://127.0.0.1:" + socket.getLocalPort() + "/";
        loop = new Thread(() -> {
            while (!socket.isClosed()) {
                try (Socket c = socket.accept()) { handle(c); } catch (Exception ignored) { }
            }
        });
        loop.setDaemon(true);
        loop.start();
    }

    /** A tiny HTTP/1.1 responder: enough to read one request and answer it. */
    private void handle(Socket c) throws Exception {
        InputStream in = c.getInputStream();
        ByteArrayOutputStream head = new ByteArrayOutputStream();
        int last4 = 0;
        while (true) {
            int b = in.read();
            if (b < 0) { return; }
            head.write(b);
            last4 = (last4 << 8) | b;
            if (last4 == 0x0D0A0D0A) { break; }
        }
        String[] lines = head.toString("UTF-8").split("\r\n");
        path.set(lines[0].split(" ")[1]);
        auth.set(null);
        int len = 0;
        for (String l : lines) {
            String low = l.toLowerCase();
            if (low.startsWith("authorization:")) { auth.set(l.substring(14).trim()); }
            if (low.startsWith("content-length:")) { len = Integer.parseInt(l.substring(15).trim()); }
        }
        byte[] raw = new byte[len];
        int got = 0;
        while (got < len) { int n = in.read(raw, got, len - got); if (n < 0) { break; } got += n; }
        try { body.set(new JSONObject(new String(raw, StandardCharsets.UTF_8))); } catch (Exception e) { body.set(null); }
        byte[] out = reply.getBytes(StandardCharsets.UTF_8);
        String h = "HTTP/1.1 " + status + " X\r\nContent-Type: application/json\r\nContent-Length: " + out.length + "\r\nConnection: close\r\n\r\n";
        c.getOutputStream().write(h.getBytes(StandardCharsets.UTF_8));
        c.getOutputStream().write(out);
        c.getOutputStream().flush();
    }

    @After public void stop() throws Exception { socket.close(); }

    private SoftphoneApi paired(MemoryStore s) {
        SoftphoneApi api = new SoftphoneApi(base, s);
        api.savePairingToken("1|abcdefghijklmnopqrstuvwxyz0123456789");
        return api;
    }

    @Test public void tokenSendsBearerDeviceAndPlatformAndParsesTheReply() throws Exception {
        reply = "{\"token\":\"jwt.value.here\",\"identity\":\"naara_user_7\",\"ttl\":3600,\"push\":true}";
        MemoryStore s = new MemoryStore();
        SoftphoneApi.TokenResult t = paired(s).token();
        assertEquals("/api/softphone/token", path.get());
        assertEquals("Bearer 1|abcdefghijklmnopqrstuvwxyz0123456789", auth.get());
        assertEquals("android", body.get().getString("platform"));
        assertTrue(DeviceId.isValid(body.get().getString("device_id")));
        assertEquals(s.get("device_id"), body.get().getString("device_id"));
        assertEquals("jwt.value.here", t.accessToken);
        assertEquals("naara_user_7", t.identity);
        assertTrue(t.push);
    }

    @Test public void pushFalseIsReportedNotHidden() throws Exception {
        reply = "{\"token\":\"jwt\",\"identity\":\"naara_user_7\",\"ttl\":3600,\"push\":false}";
        assertFalse(paired(new MemoryStore()).token().push);
    }

    @Test public void statusCodesMapToWhatTheAppShouldDo() {
        MemoryStore s = new MemoryStore();
        SoftphoneApi api = paired(s);
        int[] codes = {404, 403, 401, 429, 500};
        ApiException.Kind[] kinds = {ApiException.Kind.MODULE_OFF, ApiException.Kind.PAIR_AGAIN, ApiException.Kind.PAIR_AGAIN, ApiException.Kind.THROTTLED, ApiException.Kind.OTHER};
        for (int i = 0; i < codes.length; i++) {
            status = codes[i];
            reply = "{\"message\":\"x\"}";
            try { api.token(); fail("expected " + codes[i]); } catch (ApiException e) { assertEquals(kinds[i], e.kind); assertEquals(codes[i], e.status); }
        }
    }

    @Test public void unpairedPhoneNeverCallsTheServer() {
        SoftphoneApi api = new SoftphoneApi(base, new MemoryStore());
        path.set(null);
        try { api.token(); fail(); } catch (ApiException e) { assertEquals(ApiException.Kind.PAIR_AGAIN, e.kind); }
        assertNull(path.get());
    }

    @Test public void unpairForgetsTheTokenEvenWhenTheServerIsGone() throws Exception {
        MemoryStore s = new MemoryStore();
        SoftphoneApi api = paired(s);
        socket.close();
        api.unpair();
        assertFalse(api.isPaired());
        assertNull(s.get(SoftphoneApi.PAIRING_KEY));
    }

    @Test public void unpairTellsTheServerFirst() {
        MemoryStore s = new MemoryStore();
        SoftphoneApi api = paired(s);
        reply = "{\"ok\":true}";
        api.unpair();
        assertEquals("/api/softphone/unpair", path.get());
        assertFalse(api.isPaired());
    }

    @Test public void aServerWithoutATokenInTheReplyIsAnError() {
        reply = "{\"identity\":\"naara_user_7\"}";
        try { paired(new MemoryStore()).token(); fail(); } catch (ApiException e) { assertEquals(502, e.status); }
    }

    @Test public void junkPairingTokensAreRefused() {
        SoftphoneApi api = new SoftphoneApi(base, new MemoryStore());
        api.savePairingToken("short");
        api.savePairingToken(null);
        assertFalse(api.isPaired());
    }

    @Test public void trailingSlashesAreTrimmed() {
        assertEquals("https://x.test", SoftphoneApi.normalizeBase(" https://x.test// "));
    }
}
