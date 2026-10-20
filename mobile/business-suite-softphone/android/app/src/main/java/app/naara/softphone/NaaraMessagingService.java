package app.naara.softphone;

import android.util.Log;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;
import com.twilio.voice.CallException;
import com.twilio.voice.CallInvite;
import com.twilio.voice.CancelledCallInvite;
import com.twilio.voice.MessageListener;
import com.twilio.voice.Voice;

/** Receives Twilio's high-priority data push, even with the app closed, and turns it into the ringing screen. */
public class NaaraMessagingService extends FirebaseMessagingService implements MessageListener {
    @Override
    public void onMessageReceived(@NonNull RemoteMessage message) {
        if (!message.getData().isEmpty() && !Voice.handleMessage(this, message.getData(), this)) {
            Log.w("NaaraSoftphone", "Ignored a push that was not a Twilio Voice payload");
        }
    }

    /** A new FCM token means Twilio must learn it, or this phone silently stops ringing. */
    @Override
    public void onNewToken(@NonNull String token) {
        VoiceEngine.get().register(getApplicationContext());
    }

    @Override
    public void onCallInvite(@NonNull CallInvite callInvite) {
        CallService.start(this, CallService.ACTION_INCOMING, CallService.EXTRA_INVITE, callInvite);
    }

    @Override
    public void onCancelledCallInvite(@NonNull CancelledCallInvite cancelled, @Nullable CallException error) {
        CallService.start(this, CallService.ACTION_CANCEL, CallService.EXTRA_CANCELLED, cancelled);
    }
}
