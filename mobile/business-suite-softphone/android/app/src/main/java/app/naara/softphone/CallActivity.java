package app.naara.softphone;

import android.Manifest;
import android.app.KeyguardManager;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.media.AudioManager;
import android.os.Bundle;
import android.view.View;
import android.view.WindowManager;
import android.widget.TextView;

import androidx.appcompat.app.AppCompatActivity;
import androidx.core.content.ContextCompat;

/** The call screen: shows over the lock screen when a call rings, then mute, speaker and end. */
public class CallActivity extends AppCompatActivity implements VoiceEngine.Listener {
    public static final String EXTRA_NEED_MIC = "need_mic";
    private static final int REQ_MIC = 7;

    private TextView who;
    private TextView state;
    private View answerRow;
    private View activeRow;
    private TextView muteLabel;
    private TextView speakerLabel;

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        setShowWhenLocked(true);
        setTurnScreenOn(true);
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        getSystemService(KeyguardManager.class).requestDismissKeyguard(this, new KeyguardManager.KeyguardDismissCallback() { });
        setContentView(R.layout.activity_call);
        who = findViewById(R.id.call_who);
        state = findViewById(R.id.call_state);
        answerRow = findViewById(R.id.row_answer);
        activeRow = findViewById(R.id.row_active);
        muteLabel = findViewById(R.id.label_mute);
        speakerLabel = findViewById(R.id.label_speaker);
        findViewById(R.id.btn_answer).setOnClickListener(v -> send(CallService.ACTION_ACCEPT));
        findViewById(R.id.btn_decline).setOnClickListener(v -> send(CallService.ACTION_REJECT));
        findViewById(R.id.btn_end).setOnClickListener(v -> send(CallService.ACTION_HANGUP));
        findViewById(R.id.btn_mute).setOnClickListener(v -> VoiceEngine.get().setMuted(!VoiceEngine.get().muted()));
        findViewById(R.id.btn_speaker).setOnClickListener(v -> {
            boolean on = !VoiceEngine.get().speaker();
            getSystemService(AudioManager.class).setSpeakerphoneOn(on);
            VoiceEngine.get().setSpeaker(on);
        });
        if (getIntent().getBooleanExtra(EXTRA_NEED_MIC, false)) { askMic(); }
    }

    private void askMic() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(new String[] {Manifest.permission.RECORD_AUDIO}, REQ_MIC);
        } else {
            send(CallService.ACTION_ACCEPT);
        }
    }

    @Override
    public void onRequestPermissionsResult(int code, String[] perms, int[] results) {
        super.onRequestPermissionsResult(code, perms, results);
        if (code == REQ_MIC) {
            send(results.length > 0 && results[0] == PackageManager.PERMISSION_GRANTED ? CallService.ACTION_ACCEPT : CallService.ACTION_REJECT);
        }
    }

    private void send(String action) {
        startService(new Intent(this, CallService.class).setAction(action));
    }

    @Override protected void onStart() { super.onStart(); VoiceEngine.get().addListener(this); render(); }

    @Override protected void onStop() { super.onStop(); VoiceEngine.get().removeListener(this); }

    @Override public void onEngineChanged() { render(); }

    private void render() {
        VoiceEngine e = VoiceEngine.get();
        who.setText(e.caller().isEmpty() ? getString(R.string.app_name) : e.caller());
        switch (e.phase()) {
            case RINGING:
                state.setText(R.string.incoming_call);
                answerRow.setVisibility(View.VISIBLE);
                activeRow.setVisibility(View.GONE);
                break;
            case CONNECTING:
                state.setText(R.string.connecting);
                answerRow.setVisibility(View.GONE);
                activeRow.setVisibility(View.VISIBLE);
                break;
            case ACTIVE:
                state.setText(R.string.call_in_progress);
                answerRow.setVisibility(View.GONE);
                activeRow.setVisibility(View.VISIBLE);
                break;
            default:
                finish();
                return;
        }
        muteLabel.setText(e.muted() ? R.string.unmute : R.string.mute);
        speakerLabel.setText(e.speaker() ? R.string.speaker_on : R.string.speaker);
    }
}
