package app.naara.softphone;

import android.content.Context;
import android.os.Handler;
import android.os.Looper;
import android.util.Log;

import com.google.firebase.FirebaseApp;
import com.google.firebase.messaging.FirebaseMessaging;
import com.twilio.voice.Call;
import com.twilio.voice.CallException;
import com.twilio.voice.CallInvite;
import com.twilio.voice.RegistrationException;
import com.twilio.voice.RegistrationListener;
import com.twilio.voice.Voice;

import java.util.concurrent.CopyOnWriteArrayList;

/** One place that owns registration, the ringing invite and the live call. Screens only observe it. */
public final class VoiceEngine {
    private static final String TAG = "NaaraSoftphone";

    public enum Phase { IDLE, RINGING, CONNECTING, ACTIVE }

    /** What the status banner shows. */
    public enum Registration { UNPAIRED, REGISTERING, READY, NO_PUSH_CREDENTIAL, NO_FIREBASE, PAIR_AGAIN, MODULE_OFF, ERROR }

    public interface Listener { void onEngineChanged(); }

    private static final VoiceEngine INSTANCE = new VoiceEngine();

    public static VoiceEngine get() { return INSTANCE; }

    private final Handler main = new Handler(Looper.getMainLooper());
    private final CopyOnWriteArrayList<Listener> listeners = new CopyOnWriteArrayList<>();

    private volatile Registration registration = Registration.UNPAIRED;
    private Phase phase = Phase.IDLE;
    private CallInvite invite;
    private Call call;
    private boolean muted;
    private boolean speaker;
    private String caller = "";

    public Registration registration() { return registration; }

    public Phase phase() { return phase; }

    public String caller() { return caller; }

    public boolean muted() { return muted; }

    public boolean speaker() { return speaker; }

    public CallInvite invite() { return invite; }

    public void addListener(Listener l) { listeners.add(l); }

    public void removeListener(Listener l) { listeners.remove(l); }

    private void notifyChanged() {
        main.post(() -> { for (Listener l : listeners) { l.onEngineChanged(); } });
    }

    private void setRegistration(Registration r) {
        registration = r;
        notifyChanged();
    }

    /** After pairing, on every launch and when FCM issues a new token. Safe to call often. */
    public void register(Context context) {
        final Context app = context.getApplicationContext();
        final SoftphoneApi api = NaaraApp.api(app);
        if (!api.isPaired()) {
            setRegistration(Registration.UNPAIRED);
            return;
        }
        if (FirebaseApp.getApps(app).isEmpty()) {
            setRegistration(Registration.NO_FIREBASE);
            return;
        }
        setRegistration(Registration.REGISTERING);
        FirebaseMessaging.getInstance().getToken().addOnCompleteListener(task -> {
            if (!task.isSuccessful() || task.getResult() == null) {
                setRegistration(Registration.ERROR);
                return;
            }
            final String fcm = task.getResult();
            new Thread(() -> {
                try {
                    SoftphoneApi.TokenResult t = api.token();
                    if (!t.push) {
                        setRegistration(Registration.NO_PUSH_CREDENTIAL);
                        return;
                    }
                    Voice.register(t.accessToken, Voice.RegistrationChannel.FCM, fcm, new RegistrationListener() {
                        @Override public void onRegistered(String accessToken, String fcmToken) { setRegistration(Registration.READY); }

                        @Override public void onError(RegistrationException e, String accessToken, String fcmToken) {
                            Log.w(TAG, "register failed: " + e.getErrorCode() + " " + e.getMessage());
                            setRegistration(Registration.ERROR);
                        }
                    });
                } catch (ApiException e) {
                    setRegistration(e.kind == ApiException.Kind.MODULE_OFF ? Registration.MODULE_OFF : e.kind == ApiException.Kind.PAIR_AGAIN ? Registration.PAIR_AGAIN : Registration.ERROR);
                }
            }, "naara-register").start();
        });
    }

    /** Screens are rendered in tests without a live Twilio call. */
    void setStateForTests(Phase p, String who, Registration r, boolean muted, boolean speaker) {
        phase = p;
        caller = who;
        registration = r;
        this.muted = muted;
        this.speaker = speaker;
        notifyChanged();
    }

    /** Unpaired or logged out: forget everything about calls. */
    public void reset() {
        invite = null;
        call = null;
        phase = Phase.IDLE;
        setRegistration(Registration.UNPAIRED);
    }

    // ---- call lifecycle (all on the main thread; CallService is the only caller) ----

    public void onInvite(CallInvite i) {
        invite = i;
        caller = prettyCaller(i);
        phase = Phase.RINGING;
        notifyChanged();
    }

    public void onInviteCancelled(String callSid) {
        if (invite != null && (callSid == null || callSid.equals(invite.getCallSid()))) {
            invite = null;
            phase = Phase.IDLE;
            notifyChanged();
        }
    }

    public void accept(Context ctx, Call.Listener extra) {
        if (invite == null) { return; }
        phase = Phase.CONNECTING;
        call = invite.accept(ctx, new Call.Listener() {
            @Override public void onRinging(Call c) {}

            @Override public void onConnectFailure(Call c, CallException e) { end(extra, c, e, true); }

            @Override public void onConnected(Call c) { phase = Phase.ACTIVE; notifyChanged(); extra.onConnected(c); }

            @Override public void onReconnecting(Call c, CallException e) {}

            @Override public void onReconnected(Call c) {}

            @Override public void onDisconnected(Call c, CallException e) { end(extra, c, e, false); }
        });
        invite = null;
        notifyChanged();
    }

    private void end(Call.Listener extra, Call c, CallException e, boolean failed) {
        call = null;
        phase = Phase.IDLE;
        muted = false;
        speaker = false;
        notifyChanged();
        if (failed) { extra.onConnectFailure(c, e); } else { extra.onDisconnected(c, e); }
    }

    public void reject(Context ctx) {
        if (invite != null) {
            invite.reject(ctx);
            invite = null;
        }
        phase = Phase.IDLE;
        notifyChanged();
    }

    public void hangUp() {
        if (call != null) { call.disconnect(); }
    }

    public void setMuted(boolean m) {
        if (call != null) { call.mute(m); muted = m; notifyChanged(); }
    }

    public void setSpeaker(boolean on) {
        speaker = on;
        notifyChanged();
    }

    static String prettyCaller(CallInvite i) {
        String from = i.getFrom();
        if (from == null || from.isEmpty()) { return "Naara call"; }

        return from.startsWith("client:") ? from.substring("client:".length()) : from;
    }
}
