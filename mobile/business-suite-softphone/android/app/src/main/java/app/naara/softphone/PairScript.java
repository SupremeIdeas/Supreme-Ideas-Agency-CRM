package app.naara.softphone;

/**
 * The script the shell injects into the signed-in web view. The web view's own session cookie asks the server for a
 * one-purpose token, hands it to native storage and never keeps it. A logout form first tells the phone to unpair.
 */
public final class PairScript {
    private PairScript() {}

    public static String build() {
        return "(function(){"
            + "if(!window.NaaraNative||window.__naaraPairing){return;}"
            + "window.__naaraPairing=true;"
            + "document.addEventListener('submit',function(e){var f=e.target;"
            + "if(f&&f.action&&/\\/logout\\/?$/.test(f.action)){try{NaaraNative.unpair();}catch(x){}}},true);"
            + "if(NaaraNative.isPaired()){return;}"
            + "var m=document.querySelector('meta[name=csrf-token]');if(!m){return;}"
            + "fetch('/softphone/native/pair',{method:'POST',credentials:'same-origin',"
            + "headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':m.content},"
            + "body:JSON.stringify({device_id:NaaraNative.deviceId(),platform:NaaraNative.platform(),app_version:NaaraNative.appVersion(),os_version:NaaraNative.osVersion()})})"
            + ".then(function(r){return r.ok?r.json():null;})"
            + ".then(function(j){if(j&&j.token){NaaraNative.savePairingToken(j.token);}})"
            + ".catch(function(){});"
            + "})();";
    }
}
