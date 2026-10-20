import Foundation

/// What the signed-in web view runs. The shim stands in for Android's addJavascriptInterface: same NaaraNative API, so one script serves both phones.
enum PairScript {
    static func shim(deviceId: String, appVersion: String, osVersion: String, paired: Bool) -> String {
        """
        window.NaaraNative={deviceId:function(){return '\(deviceId)';},platform:function(){return 'ios';},appVersion:function(){return '\(appVersion)';},osVersion:function(){return '\(osVersion)';},\
        isPaired:function(){return \(paired ? "true" : "false");},\
        savePairingToken:function(t){window.webkit.messageHandlers.naara.postMessage({a:'save',t:t});},\
        unpair:function(){window.webkit.messageHandlers.naara.postMessage({a:'unpair'});}};
        """
    }

    /// The same script the Android shell injects (PairScript.java); change both together.
    static let body = """
    (function(){if(!window.NaaraNative||window.__naaraPairing){return;}window.__naaraPairing=true;\
    document.addEventListener('submit',function(e){var f=e.target;if(f&&f.action&&/\\/logout\\/?$/.test(f.action)){try{NaaraNative.unpair();}catch(x){}}},true);\
    if(NaaraNative.isPaired()){return;}var m=document.querySelector('meta[name=csrf-token]');if(!m){return;}\
    fetch('/softphone/native/pair',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':m.content},\
    body:JSON.stringify({device_id:NaaraNative.deviceId(),platform:NaaraNative.platform(),app_version:NaaraNative.appVersion(),os_version:NaaraNative.osVersion()})})\
    .then(function(r){return r.ok?r.json():null;}).then(function(j){if(j&&j.token){NaaraNative.savePairingToken(j.token);}}).catch(function(){});})();
    """
}
