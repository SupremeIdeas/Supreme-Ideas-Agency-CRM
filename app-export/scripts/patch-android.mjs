#!/usr/bin/env node
// Hardens the freshly generated Capacitor android/ project for the Play Store: permissions the site needs (calls, camera, location, notifications), deep links for the
// site's own address, no backup of app data, a real release signing config, and the version from the build. Safe to run twice.
//   node patch-android.mjs <androidDir> <https-origin>
import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const [dir, origin] = process.argv.slice(2);
if (!dir || !origin) { console.error('usage: patch-android.mjs <androidDir> <origin>'); process.exit(1); }
const host = new URL(origin).hostname;

// ---- manifest
const mp = join(dir, 'app/src/main/AndroidManifest.xml');
let m = readFileSync(mp, 'utf8');
m = m.replace('android:allowBackup="true"', 'android:allowBackup="false"');
if (!m.includes('usesCleartextTraffic')) m = m.replace('<application', '<application\n        android:usesCleartextTraffic="false"');
const perms = ['ACCESS_NETWORK_STATE', 'CAMERA', 'RECORD_AUDIO', 'MODIFY_AUDIO_SETTINGS', 'ACCESS_COARSE_LOCATION', 'ACCESS_FINE_LOCATION', 'POST_NOTIFICATIONS', 'VIBRATE', 'BLUETOOTH_CONNECT', 'WAKE_LOCK'];
const add = perms.filter((p) => !m.includes('android.permission.' + p)).map((p) => `    <uses-permission android:name="android.permission.${p}" />`).join('\n');
const feats = ['camera', 'camera.autofocus', 'microphone', 'location', 'location.gps'].filter((f) => !m.includes('android.hardware.' + f + '"'))
  .map((f) => `    <uses-feature android:name="android.hardware.${f}" android:required="false" />`).join('\n');
m = m.replace('</manifest>', (add ? add + '\n' : '') + (feats ? feats + '\n' : '') + '</manifest>');
if (!m.includes('android:autoVerify')) {
  m = m.replace('</activity>', `    <intent-filter android:autoVerify="true">
                <action android:name="android.intent.action.VIEW" />
                <category android:name="android.intent.category.DEFAULT" />
                <category android:name="android.intent.category.BROWSABLE" />
                <data android:scheme="https" android:host="${host}" />
            </intent-filter>
        </activity>`);
}
writeFileSync(mp, m);

// ---- MainActivity: a tapped link to the site opens that page inside the app
const find = (d) => readdirSync(d).flatMap((f) => { const p = join(d, f); return statSync(p).isDirectory() ? find(p) : p.endsWith('MainActivity.java') ? [p] : []; });
const act = find(join(dir, 'app/src/main/java'))[0];
let j = readFileSync(act, 'utf8');
if (!j.includes('openDeepLink')) {
  j = j.replace(/public class MainActivity extends BridgeActivity \{[\s\S]*\}\s*$/, `public class MainActivity extends BridgeActivity {
    private static final String HOST = "${host}";

    /** Follow links (Instagram, X, TikTok, YouTube, Facebook, Telegram, WhatsApp...) go straight to that app when it is installed, in a new task so Back returns to Naara. */
    private static final java.util.Map<String, String> SOCIAL = new java.util.HashMap<String, String>() {{
        put("instagram.com", "com.instagram.android"); put("threads.net", "com.instagram.barcelona"); put("x.com", "com.twitter.android"); put("twitter.com", "com.twitter.android");
        put("tiktok.com", "com.zhiliaoapp.musically"); put("youtube.com", "com.google.android.youtube"); put("youtu.be", "com.google.android.youtube");
        put("facebook.com", "com.facebook.katana"); put("fb.com", "com.facebook.katana"); put("t.me", "org.telegram.messenger"); put("wa.me", "com.whatsapp");
        put("whatsapp.com", "com.whatsapp"); put("linkedin.com", "com.linkedin.android"); put("snapchat.com", "com.snapchat.android"); put("pinterest.com", "com.pinterest"); put("open.spotify.com", "com.spotify.music");
    }};

    private boolean openInSocialApp(android.net.Uri u) {
        String h = u.getHost() == null ? "" : u.getHost().toLowerCase(java.util.Locale.ROOT).replaceFirst("^(www|m|mobile)\\\\.", "");
        String pkg = SOCIAL.get(h);
        if (pkg == null) return false;
        try {
            android.content.Intent i = new android.content.Intent(android.content.Intent.ACTION_VIEW, u);
            i.setPackage(pkg);
            i.addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK);
            startActivity(i);
            return true;
        } catch (Exception e) {
            return false;   // the app is not installed: the normal path (the browser) takes over
        }
    }

    private class SocialWebViewClient extends com.getcapacitor.BridgeWebViewClient {
        SocialWebViewClient(com.getcapacitor.Bridge bridge) { super(bridge); }

        @Override
        public boolean shouldOverrideUrlLoading(android.webkit.WebView view, android.webkit.WebResourceRequest request) {
            android.net.Uri u = request.getUrl();
            if ("https".equals(u.getScheme()) && !HOST.equalsIgnoreCase(u.getHost()) && openInSocialApp(u)) return true;
            return super.shouldOverrideUrlLoading(view, request);
        }
    }

    /** The site asks for the screen to be hidden from screenshots and recording only while a protected video plays (window.NaaraSecure.set(true/false)). */
    public class SecureBridge {
        @android.webkit.JavascriptInterface
        public void set(final boolean on) {
            runOnUiThread(() -> {
                if (on) getWindow().setFlags(android.view.WindowManager.LayoutParams.FLAG_SECURE, android.view.WindowManager.LayoutParams.FLAG_SECURE);
                else getWindow().clearFlags(android.view.WindowManager.LayoutParams.FLAG_SECURE);
            });
        }
    }

    /** A Capacitor WebView ignores file downloads by default. Hand them to the system download manager (with the signed-in cookie), so invoices, eSIM PDFs and books save to Downloads. */
    private void enableDownloads() {
        if (getBridge() == null || getBridge().getWebView() == null) return;
        getBridge().getWebView().addJavascriptInterface(new SecureBridge(), "NaaraSecure");
        getBridge().getWebView().setDownloadListener((url, userAgent, contentDisposition, mimeType, length) -> {
            if (url == null || !url.startsWith("https://")) return;
            try {
                android.app.DownloadManager.Request r = new android.app.DownloadManager.Request(android.net.Uri.parse(url));
                String name = android.webkit.URLUtil.guessFileName(url, contentDisposition, mimeType);
                r.setMimeType(mimeType);
                r.addRequestHeader("User-Agent", userAgent);
                String cookie = android.webkit.CookieManager.getInstance().getCookie(url);
                if (cookie != null) r.addRequestHeader("Cookie", cookie);
                r.setTitle(name);
                r.setNotificationVisibility(android.app.DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
                r.setDestinationInExternalPublicDir(android.os.Environment.DIRECTORY_DOWNLOADS, name);
                ((android.app.DownloadManager) getSystemService(DOWNLOAD_SERVICE)).enqueue(r);
                android.widget.Toast.makeText(this, name, android.widget.Toast.LENGTH_SHORT).show();
            } catch (Exception e) {
                android.widget.Toast.makeText(this, "Could not download this file", android.widget.Toast.LENGTH_LONG).show();
            }
        });
    }

    @Override
    public void onCreate(android.os.Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (getBridge() != null) getBridge().setWebViewClient(new SocialWebViewClient(getBridge()));
        enableDownloads();
        openDeepLink(getIntent());
    }

    @Override
    protected void onNewIntent(android.content.Intent intent) {
        super.onNewIntent(intent);
        openDeepLink(intent);
    }

    /** https://HOST/... links (from the browser, a message, a QR code) open that page in the app. Anything else is ignored. */
    private void openDeepLink(android.content.Intent intent) {
        android.net.Uri u = intent == null ? null : intent.getData();
        if (u != null && "https".equals(u.getScheme()) && HOST.equalsIgnoreCase(u.getHost()) && getBridge() != null && getBridge().getWebView() != null) {
            getBridge().getWebView().post(() -> getBridge().getWebView().loadUrl(u.toString()));
        }
    }
}
`);
  writeFileSync(act, j);
}

// ---- gradle: version from the build, and a real release signing config
const gp = join(dir, 'app/build.gradle');
let gr = readFileSync(gp, 'utf8');
if (!gr.includes('signingConfigs')) {
  gr = gr.replace(/versionCode 1\s+versionName "1\.0"/, `versionCode = Integer.parseInt((project.findProperty('versionCode') ?: '1').toString())
        versionName = (project.findProperty('versionName') ?: '1.0').toString()`);
  gr = gr.replace('    buildTypes {', `    signingConfigs {
        release {
            // supplied by the environment, never stored in the repo (see app-export/README.md)
            def ks = System.getenv('NAARA_KEYSTORE_FILE')
            if (ks) {
                storeFile file(ks)
                storePassword System.getenv('NAARA_STORE_PASSWORD')
                keyAlias System.getenv('NAARA_KEY_ALIAS')
                keyPassword System.getenv('NAARA_KEY_PASSWORD')
            }
        }
    }
    buildTypes {`);
  gr = gr.replace("release {\n            minifyEnabled false", "release {\n            signingConfig signingConfigs.release\n            minifyEnabled false");
  writeFileSync(gp, gr);
}
console.log('android project patched for', host);
