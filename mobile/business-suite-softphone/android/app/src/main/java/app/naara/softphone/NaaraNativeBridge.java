package app.naara.softphone;

import android.content.Context;
import android.os.Build;
import android.webkit.JavascriptInterface;

/** What the signed-in web view can ask of the shell. It can hand over a pairing token; it can never read one back. */
public class NaaraNativeBridge {
    private final Context ctx;
    private final Runnable onPairingChanged;

    public NaaraNativeBridge(Context ctx, Runnable onPairingChanged) {
        this.ctx = ctx.getApplicationContext();
        this.onPairingChanged = onPairingChanged;
    }

    @JavascriptInterface public String deviceId() { return DeviceId.get(NaaraApp.store(ctx)); }

    @JavascriptInterface public String platform() { return "android"; }

    @JavascriptInterface public String appVersion() { return BuildConfig.VERSION_NAME; }

    @JavascriptInterface public String osVersion() { return Build.VERSION.RELEASE; }

    @JavascriptInterface public boolean isPaired() { return NaaraApp.api(ctx).isPaired(); }

    @JavascriptInterface public void savePairingToken(String token) {
        NaaraApp.api(ctx).savePairingToken(token);
        onPairingChanged.run();
    }

    /** Called by the logout form before the session ends, so the token is revoked while it still can be. */
    @JavascriptInterface public void unpair() {
        new Thread(() -> {
            NaaraApp.api(ctx).unpair();
            VoiceEngine.get().reset();
            onPairingChanged.run();
        }, "naara-unpair").start();
    }
}
