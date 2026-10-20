package app.naara.softphone;

import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;

import androidx.security.crypto.EncryptedSharedPreferences;
import androidx.security.crypto.MasterKey;

/** The pairing token lives in the Android Keystore backed EncryptedSharedPreferences, never in web storage. */
public class PrefsStore implements KeyValueStore {
    private final SharedPreferences prefs;

    public PrefsStore(Context ctx) {
        SharedPreferences p;
        try {
            MasterKey key = new MasterKey.Builder(ctx).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build();
            p = EncryptedSharedPreferences.create(ctx, "naara_softphone", key,
                EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV, EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM);
        } catch (Exception e) {
            // a broken Keystore (rare, some OEM restores): start clean rather than crash; the person simply pairs again
            Log.w("NaaraSoftphone", "Keystore unavailable, resetting storage: " + e.getMessage());
            ctx.deleteSharedPreferences("naara_softphone");
            try {
                MasterKey key = new MasterKey.Builder(ctx).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build();
                p = EncryptedSharedPreferences.create(ctx, "naara_softphone", key,
                    EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV, EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM);
            } catch (Exception again) {
                throw new IllegalStateException(again);
            }
        }
        this.prefs = p;
    }

    @Override public String get(String key) { return prefs.getString(key, null); }

    @Override public void put(String key, String value) { prefs.edit().putString(key, value).apply(); }

    @Override public void remove(String key) { prefs.edit().remove(key).apply(); }
}
