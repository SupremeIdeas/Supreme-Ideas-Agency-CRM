package app.naara.softphone;

/** A non-2xx answer from the Naara server, with what the app should do about it. */
public class ApiException extends Exception {
    public enum Kind {
        /** 404: the Native softphone module is switched off on the server. */
        MODULE_OFF,
        /** 401/403: the token is gone, expired, revoked, or the person is no longer a workspace member. Pair again. */
        PAIR_AGAIN,
        /** 429: slow down. */
        THROTTLED,
        /** Anything else, including no network. */
        OTHER
    }

    public final int status;
    public final Kind kind;

    public ApiException(int status, String message) {
        super(message);
        this.status = status;
        this.kind = status == 404 ? Kind.MODULE_OFF : (status == 401 || status == 403) ? Kind.PAIR_AGAIN : status == 429 ? Kind.THROTTLED : Kind.OTHER;
    }
}
