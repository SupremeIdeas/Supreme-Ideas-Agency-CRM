package app.naara.softphone;

/** Tiny storage seam so the pairing logic runs on a plain JVM in tests. The Android implementation is encrypted. */
public interface KeyValueStore {
    String get(String key);

    void put(String key, String value);

    void remove(String key);
}
