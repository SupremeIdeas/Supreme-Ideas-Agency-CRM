package app.naara.softphone;

import android.Manifest;
import android.app.Notification;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.content.pm.ServiceInfo;
import android.media.AudioManager;
import android.os.IBinder;

import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;
import androidx.core.app.Person;
import androidx.core.content.ContextCompat;

import com.twilio.voice.Call;
import com.twilio.voice.CallException;
import com.twilio.voice.CallInvite;
import com.twilio.voice.CancelledCallInvite;

/** Owns the ringing notification and the in-call foreground notification. Started by the push service and by notification buttons. */
public class CallService extends Service {
    public static final String ACTION_INCOMING = "app.naara.softphone.INCOMING";
    public static final String ACTION_CANCEL = "app.naara.softphone.CANCEL";
    public static final String ACTION_ACCEPT = "app.naara.softphone.ACCEPT";
    public static final String ACTION_REJECT = "app.naara.softphone.REJECT";
    public static final String ACTION_HANGUP = "app.naara.softphone.HANGUP";
    public static final String EXTRA_INVITE = "invite";
    public static final String EXTRA_CANCELLED = "cancelled";
    private static final int RING_ID = 4101;
    private static final int ACTIVE_ID = 4102;

    private final Call.Listener callEnded = new Call.Listener() {
        @Override public void onRinging(Call c) {}

        @Override public void onConnectFailure(Call c, CallException e) { finishCall(); }

        @Override public void onConnected(Call c) { setAudioMode(true); }

        @Override public void onReconnecting(Call c, CallException e) {}

        @Override public void onReconnected(Call c) {}

        @Override public void onDisconnected(Call c, CallException e) { finishCall(); }
    };

    @Override public IBinder onBind(Intent intent) { return null; }

    @Override
    public int onStartCommand(@Nullable Intent intent, int flags, int startId) {
        String action = intent == null ? null : intent.getAction();
        VoiceEngine engine = VoiceEngine.get();
        if (ACTION_INCOMING.equals(action)) {
            CallInvite invite = intent.getParcelableExtra(EXTRA_INVITE);
            if (invite != null) {
                engine.onInvite(invite);
                notifyRinging();
            }
        } else if (ACTION_CANCEL.equals(action)) {
            CancelledCallInvite c = intent.getParcelableExtra(EXTRA_CANCELLED);
            engine.onInviteCancelled(c == null ? null : c.getCallSid());
            clearRinging();
            stopSelfIfIdle();
        } else if (ACTION_ACCEPT.equals(action)) {
            accept();
        } else if (ACTION_REJECT.equals(action)) {
            engine.reject(this);
            clearRinging();
            stopSelf();
        } else if (ACTION_HANGUP.equals(action)) {
            engine.hangUp();
        }

        return START_NOT_STICKY;
    }

    private void accept() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
            // cannot open the microphone yet: bring the call screen up, which asks, then sends ACCEPT again
            startActivity(new Intent(this, CallActivity.class).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK).putExtra(CallActivity.EXTRA_NEED_MIC, true));
            return;
        }
        clearRinging();
        startForeground(ACTIVE_ID, activeNotification(), ServiceInfo.FOREGROUND_SERVICE_TYPE_MICROPHONE);
        VoiceEngine.get().accept(this, callEnded);
        startActivity(new Intent(this, CallActivity.class).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK));
    }

    private void finishCall() {
        setAudioMode(false);
        stopForeground(STOP_FOREGROUND_REMOVE);
        stopSelf();
    }

    private void stopSelfIfIdle() {
        if (VoiceEngine.get().phase() == VoiceEngine.Phase.IDLE) { stopSelf(); }
    }

    private void setAudioMode(boolean inCall) {
        AudioManager am = getSystemService(AudioManager.class);
        am.setMode(inCall ? AudioManager.MODE_IN_COMMUNICATION : AudioManager.MODE_NORMAL);
        if (!inCall) { am.setSpeakerphoneOn(false); }
    }

    private PendingIntent service(String action, int req) {
        return PendingIntent.getService(this, req, new Intent(this, CallService.class).setAction(action), PendingIntent.FLAG_IMMUTABLE | PendingIntent.FLAG_UPDATE_CURRENT);
    }

    private void notifyRinging() {
        String who = VoiceEngine.get().caller();
        PendingIntent full = PendingIntent.getActivity(this, 1, new Intent(this, CallActivity.class).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK), PendingIntent.FLAG_IMMUTABLE | PendingIntent.FLAG_UPDATE_CURRENT);
        Person person = new Person.Builder().setName(who).setImportant(true).build();
        Notification n = new NotificationCompat.Builder(this, NaaraApp.CHANNEL_INCOMING)
            .setSmallIcon(R.drawable.ic_call)
            .setContentTitle(who)
            .setContentText(getString(R.string.incoming_call))
            .setCategory(NotificationCompat.CATEGORY_CALL)
            .setPriority(NotificationCompat.PRIORITY_MAX)
            .setOngoing(true)
            .setAutoCancel(false)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setFullScreenIntent(full, true)
            .setContentIntent(full)
            .setStyle(NotificationCompat.CallStyle.forIncomingCall(person, service(ACTION_REJECT, 2), service(ACTION_ACCEPT, 3)))
            .build();
        getSystemService(NotificationManager.class).notify(RING_ID, n);
    }

    private Notification activeNotification() {
        PendingIntent open = PendingIntent.getActivity(this, 4, new Intent(this, CallActivity.class), PendingIntent.FLAG_IMMUTABLE | PendingIntent.FLAG_UPDATE_CURRENT);
        Person person = new Person.Builder().setName(VoiceEngine.get().caller()).build();
        return new NotificationCompat.Builder(this, NaaraApp.CHANNEL_ACTIVE)
            .setSmallIcon(R.drawable.ic_call)
            .setContentTitle(VoiceEngine.get().caller())
            .setContentText(getString(R.string.call_in_progress))
            .setCategory(NotificationCompat.CATEGORY_CALL)
            .setOngoing(true)
            .setContentIntent(open)
            .setStyle(NotificationCompat.CallStyle.forOngoingCall(person, service(ACTION_HANGUP, 5)))
            .build();
    }

    private void clearRinging() { getSystemService(NotificationManager.class).cancel(RING_ID); }

    /** Convenience for the push service. */
    public static void start(Context c, String action, String extra, android.os.Parcelable value) {
        Intent i = new Intent(c, CallService.class).setAction(action);
        if (extra != null) { i.putExtra(extra, value); }
        c.startService(i);
    }
}
