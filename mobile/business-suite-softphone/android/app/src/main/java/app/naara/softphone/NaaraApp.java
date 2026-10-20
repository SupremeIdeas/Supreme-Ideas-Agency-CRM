package app.naara.softphone;

import android.app.Application;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.media.AudioAttributes;
import android.media.RingtoneManager;

public class NaaraApp extends Application {
    public static final String CHANNEL_INCOMING = "naara_incoming_calls";
    public static final String CHANNEL_ACTIVE = "naara_active_call";

    private static KeyValueStore store;
    private static SoftphoneApi api;

    @Override
    public void onCreate() {
        super.onCreate();
        NotificationManager nm = getSystemService(NotificationManager.class);
        NotificationChannel in = new NotificationChannel(CHANNEL_INCOMING, getString(R.string.channel_incoming), NotificationManager.IMPORTANCE_HIGH);
        in.setSound(RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE),
            new AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_NOTIFICATION_RINGTONE).setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION).build());
        in.enableVibration(true);
        in.setLockscreenVisibility(android.app.Notification.VISIBILITY_PUBLIC);
        nm.createNotificationChannel(in);
        NotificationChannel active = new NotificationChannel(CHANNEL_ACTIVE, getString(R.string.channel_active), NotificationManager.IMPORTANCE_LOW);
        nm.createNotificationChannel(active);
    }

    /** Tests supply an in-memory store (the Keystore is not available on a plain JVM). */
    static synchronized void useStoreForTests(KeyValueStore s) {
        store = s;
        api = null;
    }

    public static synchronized KeyValueStore store(android.content.Context c) {
        if (store == null) {
            store = new PrefsStore(c.getApplicationContext());
        }
        return store;
    }

    public static synchronized SoftphoneApi api(android.content.Context c) {
        if (api == null) {
            api = new SoftphoneApi(BuildConfig.NAARA_BASE_URL, store(c));
        }
        return api;
    }
}
