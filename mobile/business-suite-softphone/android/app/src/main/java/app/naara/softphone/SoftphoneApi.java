package app.naara.softphone;

import org.json.JSONException;
import org.json.JSONObject;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

/**
 * The phone's only door to the server: /api/softphone/{token,status,unpair} with the one-purpose pairing token.
 * It never sees a password, a session or a Twilio secret. Blocking calls: run off the main thread.
 */
public class SoftphoneApi {
    public static final String PAIRING_KEY = "pairing_token";

    /** What /api/softphone/token returns. push=false means the server has no push credential for this platform. */
    public static final class TokenResult {
        public final String accessToken;
        public final String identity;
        public final int ttl;
        public final boolean push;

        TokenResult(String accessToken, String identity, int ttl, boolean push) {
            this.accessToken = accessToken;
            this.identity = identity;
            this.ttl = ttl;
            this.push = push;
        }
    }

    private final String base;
    private final KeyValueStore store;

    public SoftphoneApi(String baseUrl, KeyValueStore store) {
        this.base = normalizeBase(baseUrl);
        this.store = store;
    }

    public static String normalizeBase(String url) {
        return url == null ? "" : url.trim().replaceAll("/+$", "");
    }

    public boolean isPaired() {
        String t = store.get(PAIRING_KEY);
        return t != null && !t.isEmpty();
    }

    public void savePairingToken(String token) {
        if (token != null && token.length() >= 20 && token.length() <= 200) {
            store.put(PAIRING_KEY, token);
        }
    }

    public void forgetPairing() {
        store.remove(PAIRING_KEY);
    }

    public TokenResult token() throws ApiException {
        JSONObject j = post("token");
        String jwt = j.optString("token", "");
        if (jwt.isEmpty()) {
            throw new ApiException(502, "The server did not return a call token.");
        }

        return new TokenResult(jwt, j.optString("identity", ""), j.optInt("ttl", 3600), j.optBoolean("push", false));
    }

    public boolean status() throws ApiException {
        return post("status").optBoolean("paired", false);
    }

    /** Tells the server to forget this phone and revoke the token, then forgets it here whatever the answer. */
    public void unpair() {
        try {
            post("unpair");
        } catch (ApiException ignored) {
            // the token may already be dead; the local copy is removed either way
        } finally {
            forgetPairing();
        }
    }

    private JSONObject post(String path) throws ApiException {
        String pairing = store.get(PAIRING_KEY);
        if (pairing == null || pairing.isEmpty()) {
            throw new ApiException(401, "This phone is not paired yet.");
        }
        HttpURLConnection c = null;
        try {
            c = (HttpURLConnection) new URL(base + "/api/softphone/" + path).openConnection();
            c.setRequestMethod("POST");
            c.setConnectTimeout(10000);
            c.setReadTimeout(15000);
            c.setDoOutput(true);
            c.setRequestProperty("Authorization", "Bearer " + pairing);
            c.setRequestProperty("Accept", "application/json");
            c.setRequestProperty("Content-Type", "application/json");
            byte[] body = new JSONObject().put("device_id", DeviceId.get(store)).put("platform", "android").toString().getBytes(StandardCharsets.UTF_8);
            try (OutputStream out = c.getOutputStream()) {
                out.write(body);
            }
            int code = c.getResponseCode();
            String text = read(code >= 400 ? c.getErrorStream() : c.getInputStream());
            if (code < 200 || code > 299) {
                throw new ApiException(code, text.length() > 200 ? text.substring(0, 200) : text);
            }

            return text.isEmpty() ? new JSONObject() : new JSONObject(text);
        } catch (IOException | JSONException e) {
            throw new ApiException(0, "Could not reach the server: " + e.getMessage());
        } finally {
            if (c != null) {
                c.disconnect();
            }
        }
    }

    private static String read(InputStream in) throws IOException {
        if (in == null) {
            return "";
        }
        try (InputStream i = in; ByteArrayOutputStream o = new ByteArrayOutputStream()) {
            byte[] buf = new byte[4096];
            int n;
            while ((n = i.read(buf)) > 0) {
                o.write(buf, 0, n);
            }

            return o.toString("UTF-8");
        }
    }
}
