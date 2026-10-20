package app.naara.softphone;

import static org.junit.Assert.*;
import static org.junit.Assume.assumeNotNull;

import org.json.JSONObject;
import org.junit.Test;

import java.nio.charset.StandardCharsets;
import java.util.Base64;

/**
 * Runs the phone's real client against a real Naara server. Skipped unless both variables are set:
 *   NAARA_CONTRACT_BASE_URL       e.g. http://127.0.0.1:8152
 *   NAARA_CONTRACT_PAIRING_TOKEN  the token the web view handed to the shell (tests/mobile/pair.mjs produces it)
 */
public class ServerContractTest {
    @Test public void tokenStatusAndUnpairWorkAgainstTheRealServer() throws Exception {
        String base = System.getenv("NAARA_CONTRACT_BASE_URL");
        String pairing = System.getenv("NAARA_CONTRACT_PAIRING_TOKEN");
        assumeNotNull(base, pairing);

        MemoryStore store = new MemoryStore();
        store.put("device_id", "android-contracttest0001");
        SoftphoneApi api = new SoftphoneApi(base, store);
        api.savePairingToken(pairing);

        assertTrue("server knows this phone", api.status());

        SoftphoneApi.TokenResult t = api.token();
        String[] parts = t.accessToken.split("\\.");
        assertEquals("a JWT", 3, parts.length);
        JSONObject claims = new JSONObject(new String(Base64.getUrlDecoder().decode(parts[1]), StandardCharsets.UTF_8));
        JSONObject voice = claims.getJSONObject("grants").getJSONObject("voice");
        assertTrue(t.identity.startsWith("naara_user_"));
        assertEquals(t.identity, claims.getJSONObject("grants").getString("identity"));
        assertTrue("this phone may receive calls", voice.getJSONObject("incoming").getBoolean("allow"));
        assertTrue("android push credential is in the token", t.push);
        assertTrue(voice.getString("push_credential_sid").matches("^CR[0-9a-f]{32}$"));
        assertFalse("no secret in the payload", new String(Base64.getUrlDecoder().decode(parts[1]), StandardCharsets.UTF_8).contains("keysecret"));

        api.unpair();
        assertFalse(api.isPaired());
        // the revoked token no longer works
        store.put(SoftphoneApi.PAIRING_KEY, pairing);
        try { new SoftphoneApi(base, store).token(); fail("a revoked token must be refused"); } catch (ApiException e) { assertEquals(ApiException.Kind.PAIR_AGAIN, e.kind); }
    }
}
