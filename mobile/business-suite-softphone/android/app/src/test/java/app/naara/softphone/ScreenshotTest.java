package app.naara.softphone;

import static org.junit.Assert.assertEquals;

import android.app.Activity;
import android.graphics.Bitmap;
import android.graphics.Canvas;
import android.view.View;
import java.io.File;
import java.io.FileOutputStream;
import android.widget.TextView;

import androidx.test.core.app.ApplicationProvider;

import org.junit.Before;
import org.junit.Test;
import org.junit.runner.RunWith;
import org.robolectric.Robolectric;
import org.robolectric.RobolectricTestRunner;
import org.robolectric.annotation.Config;
import org.robolectric.annotation.GraphicsMode;

/** Renders the app's real layouts on the JVM (Robolectric native graphics) and checks what they say. Images land in app/build/outputs/screenshots. */
@RunWith(RobolectricTestRunner.class)
@GraphicsMode(GraphicsMode.Mode.NATIVE)
@Config(sdk = 34, qualifiers = "w411dp-h891dp-xxhdpi")
public class ScreenshotTest {
    @Before public void setUp() {
        NaaraApp.useStoreForTests(new MemoryStore());
    }

    /** Draws the real view tree to a PNG with Robolectric's native graphics. No emulator involved. */
    private void shot(Activity a, String name) {
        try {
            View v = a.getWindow().getDecorView();
            int w = 1080, h = 2340;
            v.measure(View.MeasureSpec.makeMeasureSpec(w, View.MeasureSpec.EXACTLY), View.MeasureSpec.makeMeasureSpec(h, View.MeasureSpec.EXACTLY));
            v.layout(0, 0, w, h);
            Bitmap bmp = Bitmap.createBitmap(w, h, Bitmap.Config.ARGB_8888);
            v.draw(new Canvas(bmp));
            File dir = new File("build/outputs/screenshots");
            dir.mkdirs();
            try (FileOutputStream out = new FileOutputStream(new File(dir, name + ".png"))) {
                bmp.compress(Bitmap.CompressFormat.PNG, 100, out);
            }
        } catch (Exception e) {
            throw new RuntimeException(e);
        }
    }

    @Test public void callScreenRinging() {
        VoiceEngine.get().setStateForTests(VoiceEngine.Phase.RINGING, "+234 801 234 5678", VoiceEngine.Registration.READY, false, false);
        CallActivity a = Robolectric.buildActivity(CallActivity.class).setup().get();
        assertEquals("+234 801 234 5678", ((TextView) a.findViewById(R.id.call_who)).getText().toString());
        shot(a, "01-incoming-call");
    }

    @Test public void callScreenActive() {
        VoiceEngine.get().setStateForTests(VoiceEngine.Phase.ACTIVE, "+234 801 234 5678", VoiceEngine.Registration.READY, true, true);
        CallActivity a = Robolectric.buildActivity(CallActivity.class).setup().get();
        assertEquals("Unmute", ((TextView) a.findViewById(R.id.label_mute)).getText().toString());
        shot(a, "02-call-in-progress");
    }

    private MainActivity main(VoiceEngine.Registration r) {
        MainActivity a = Robolectric.buildActivity(MainActivity.class).setup().get();
        // onResume re-registers (and an unpaired test phone says "sign in"); set the state to show after that has run
        VoiceEngine.get().setStateForTests(VoiceEngine.Phase.IDLE, "", r, false, false);
        org.robolectric.shadows.ShadowLooper.idleMainLooper();
        return a;
    }

    @Test public void bannerReady() {
        MainActivity a = main(VoiceEngine.Registration.READY);
        assertEquals(ApplicationProvider.getApplicationContext().getString(R.string.banner_ready), ((TextView) a.findViewById(R.id.banner)).getText().toString());
        shot(a, "03-banner-ready");
    }

    @Test public void bannerNoPushCredential() {
        MainActivity a = main(VoiceEngine.Registration.NO_PUSH_CREDENTIAL);
        shot(a, "04-banner-no-push-credential");
    }

    @Test public void bannerSignIn() {
        MainActivity a = main(VoiceEngine.Registration.UNPAIRED);
        shot(a, "05-banner-sign-in");
    }
}
