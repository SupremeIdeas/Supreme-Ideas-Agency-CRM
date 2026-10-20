package app.naara.softphone;

/** Plain-language status for the banner on top of the web view. Honest about every state, including "not ringing yet". */
public final class BannerText {
    private BannerText() {}

    public static int forState(VoiceEngine.Registration r) {
        switch (r) {
            case READY: return R.string.banner_ready;
            case REGISTERING: return R.string.banner_registering;
            case NO_PUSH_CREDENTIAL: return R.string.banner_no_push;
            case NO_FIREBASE: return R.string.banner_no_firebase;
            case PAIR_AGAIN: return R.string.banner_pair_again;
            case MODULE_OFF: return R.string.banner_module_off;
            case ERROR: return R.string.banner_error;
            default: return R.string.banner_unpaired;
        }
    }

    public static boolean isGood(VoiceEngine.Registration r) { return r == VoiceEngine.Registration.READY; }
}
