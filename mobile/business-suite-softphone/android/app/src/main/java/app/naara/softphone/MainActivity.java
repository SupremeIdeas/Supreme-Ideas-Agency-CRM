package app.naara.softphone;

import android.Manifest;
import android.annotation.SuppressLint;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.TextView;

import androidx.activity.OnBackPressedCallback;
import androidx.appcompat.app.AppCompatActivity;

/** The Naara web app in a shell, plus a one-line banner saying whether this phone will ring. Everything but calling stays on the web. */
public class MainActivity extends AppCompatActivity implements VoiceEngine.Listener {
    private WebView web;
    private TextView banner;
    private String host;

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        setContentView(R.layout.activity_main);
        banner = findViewById(R.id.banner);
        web = findViewById(R.id.web);
        host = Uri.parse(BuildConfig.NAARA_BASE_URL).getHost();

        WebSettings s = web.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setMediaPlaybackRequiresUserGesture(true);
        s.setAllowFileAccess(false);
        s.setAllowContentAccess(false);
        web.addJavascriptInterface(new NaaraNativeBridge(this, () -> runOnUiThread(() -> VoiceEngine.get().register(getApplicationContext()))), "NaaraNative");
        web.setWebViewClient(new WebViewClient() {
            @Override public boolean shouldOverrideUrlLoading(WebView v, WebResourceRequest r) {
                Uri u = r.getUrl();
                if ("https".equals(u.getScheme()) && host != null && host.equalsIgnoreCase(u.getHost())) { return false; }
                startActivity(new Intent(Intent.ACTION_VIEW, u));   // outside links open in the browser, never inside the bridge page
                return true;
            }

            @Override public void onPageFinished(WebView v, String url) {
                Uri u = Uri.parse(url);
                if (host != null && host.equalsIgnoreCase(u.getHost())) { v.evaluateJavascript(PairScript.build(), null); }
            }
        });
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override public void handleOnBackPressed() {
                if (web.canGoBack()) { web.goBack(); } else { setEnabled(false); getOnBackPressedDispatcher().onBackPressed(); }
            }
        });
        if (b == null) { web.loadUrl(BuildConfig.NAARA_BASE_URL); } else { web.restoreState(b); }
        askPermissions();
    }

    private void askPermissions() {
        if (Build.VERSION.SDK_INT >= 33) {
            requestPermissions(new String[] {Manifest.permission.POST_NOTIFICATIONS, Manifest.permission.RECORD_AUDIO}, 1);
        } else {
            requestPermissions(new String[] {Manifest.permission.RECORD_AUDIO}, 1);
        }
    }

    @Override protected void onSaveInstanceState(Bundle out) { super.onSaveInstanceState(out); web.saveState(out); }

    @Override protected void onStart() { super.onStart(); VoiceEngine.get().addListener(this); }

    @Override protected void onStop() { super.onStop(); VoiceEngine.get().removeListener(this); }

    @Override protected void onResume() {
        super.onResume();
        VoiceEngine.get().register(getApplicationContext());   // refreshes the push registration on every launch
        render();
    }

    @Override public void onEngineChanged() { render(); }

    private void render() {
        VoiceEngine.Registration r = VoiceEngine.get().registration();
        banner.setText(BannerText.forState(r));
        banner.setBackgroundResource(BannerText.isGood(r) ? R.color.banner_ok : R.color.banner_warn);
    }
}
