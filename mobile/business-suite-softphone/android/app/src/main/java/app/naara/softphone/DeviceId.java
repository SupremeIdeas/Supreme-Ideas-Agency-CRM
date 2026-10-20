package app.naara.softphone;

import java.util.UUID;

/** A stable id for this install. The server accepts 8 to 64 characters of A-Z a-z 0-9 . _ - */
public final class DeviceId {
    private static final String KEY = "device_id";

    private DeviceId() {}

    public static String get(KeyValueStore store) {
        String id = store.get(KEY);
        if (id == null || !isValid(id)) {
            id = "android-" + UUID.randomUUID().toString().replace("-", "");
            store.put(KEY, id);
        }
        return id;
    }

    public static boolean isValid(String id) {
        return id != null && id.matches("^[A-Za-z0-9._-]{8,64}$");
    }
}
