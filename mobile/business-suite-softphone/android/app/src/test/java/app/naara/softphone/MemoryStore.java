package app.naara.softphone;

import java.util.HashMap;
import java.util.Map;

class MemoryStore implements KeyValueStore {
    final Map<String, String> m = new HashMap<>();

    @Override public String get(String k) { return m.get(k); }

    @Override public void put(String k, String v) { m.put(k, v); }

    @Override public void remove(String k) { m.remove(k); }
}
